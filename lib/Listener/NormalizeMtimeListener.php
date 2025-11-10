<?php
namespace OCA\RetentionNormalizeMtime\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/** @implements IEventListener<Event> */
class NormalizeMtimeListener implements IEventListener {
	public function __construct(
		private IRootFolder $rootFolder,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
		private IConfig $config
	) {}

	private function writeFileLog(string $msg): void {
		$prefix = '[' . date('c') . '] ';
		// prefer the configured data directory
		$dataDir = null;
		try {
			if (\class_exists('OC') && isset(\OC::$server)) {
				$dataDir = \OC::$server->getSystemConfig()->getValue('datadirectory', null);
			}
		} catch (\Throwable $e) {
			// ignore
		}

		// Fallback auf Server-Variable oder relativen Pfad
		if (empty($dataDir)) {
			if (!empty($_SERVER['OC_DATA_DIR'])) {
				$dataDir = $_SERVER['OC_DATA_DIR'];
			} else {
				// Von /var/www/html/web/apps/retention_normalize_mtime/lib/Listener/ nach /var/www/html/web/data/
				// sind es 6 Verzeichnisse nach oben: lib/ -> Listener/ -> retention_normalize_mtime/ -> apps/ -> web/ -> html/
				$dataDir = __DIR__ . '/../../../../../data';
			}
		}

		$logFile = rtrim($dataDir, '/\\') . '/retention_normalize_mtime.log';
		@file_put_contents($logFile, $prefix . $msg . "\n", FILE_APPEND | LOCK_EX);
		$this->logger->warning($msg, ['app'=>'retention_normalize_mtime']);
	}

	public function handle(Event $event): void {
		// Akzeptiere sowohl NodeCreatedEvent als auch NodeWrittenEvent
		if (!($event instanceof NodeCreatedEvent) && !($event instanceof NodeWrittenEvent)) {
			return;
		}

		$node = $event->getNode();
		if (!($node instanceof File)) {
			return;
		}

		try {
			$path = $node->getPath();
		} catch (\Throwable $e) {
			$this->writeFileLog('ERROR: failed to get path: ' . $e->getMessage());
			return;
		}

		$owner = $node->getOwner();
		if ($owner === null) {
			$this->writeFileLog('ERROR: owner is null, skipping for path ' . $path);
			return;
		}
		$uid = $owner->getUID();

		// Lade Filter-Einstellungen aus der Config
		$limitToGroup = $this->config->getAppValue('retention-normalize-mtime', 'limit_to_group', '');
		$limitToPrefix = $this->config->getAppValue('retention-normalize-mtime', 'limit_to_prefix', '');

		// optional: Gruppenfilter
		if ($limitToGroup && !$this->groupManager->isInGroup($uid, $limitToGroup)) {
			return;
		}

		// optional: Ordnerpräfixfilter
		if ($limitToPrefix) {
			try {
				$userFolder = $this->rootFolder->getUserFolder($uid);
				$relPath = null;

				$maybe = $path;
				$prefix1 = '/' . $uid . '/files';
				if (str_starts_with($maybe, $prefix1)) {
					$relPath = substr($maybe, strlen($prefix1));
				} elseif (str_starts_with($maybe, '/files')) {
					$relPath = substr($maybe, strlen('/files'));
				} else {
					$relPath = $userFolder->getRelativePath($path);
				}

			$rel = '/' . ltrim((string)$relPath, '/');
			if (!str_starts_with($rel, $limitToPrefix)) {
				return;
			}
			} catch (\Throwable $e) {
				$this->writeFileLog('ERROR: getRelativePath failed: ' . $e->getMessage());
				return;
			}
		}

		// mtime auf Uploadzeit setzen (keine DB-/WebDAV-Tricks)
		$now = time();
		$fileId = null;
		try {
			$fileId = $node->getId();
		} catch (\Throwable $e) {
			// ignore
		}
		// Versuche: registriere shutdown handler, der das Touch am Ende der Anfrage ausführt.
		try {
			$root = $this->rootFolder;
			$uidForShutdown = $uid;
			$pathForShutdown = $path;
			$fileIdForShutdown = $fileId;
			$log = function($m) { $this->writeFileLog($m); };
			register_shutdown_function(function() use ($root, $uidForShutdown, $pathForShutdown, $now, $log, $fileIdForShutdown) {
				try {
					$userFolder = $root->getUserFolder($uidForShutdown);
					// compute relative path
					$maybe2 = $pathForShutdown;
					$prefix1 = '/' . $uidForShutdown . '/files/';
					if (str_starts_with($maybe2, $prefix1)) {
						$rel2 = substr($maybe2, strlen($prefix1));
					} elseif (str_starts_with($maybe2, 'files/')) {
						$rel2 = substr($maybe2, strlen('files/'));
					} else {
						$rel2 = ltrim($maybe2, '/');
						$p = $uidForShutdown . '/files/';
						if (str_starts_with($rel2, $p)) {
							$rel2 = substr($rel2, strlen($p));
						}
					}
					$node2 = $userFolder->get($rel2);
					$node2->touch($now);
					// Erfolg: kein Log
				} catch (\Throwable $e) {
					$log('ERROR: shutdown touch failed for ' . $pathForShutdown . ': ' . $e->getMessage());
					// Fallback: update DB mtime if we have fileid
					if ($fileIdForShutdown) {
						try {
							$db = \OC::$server->getDatabaseConnection();
							$db->executeStatement('UPDATE oc_filecache SET mtime = ? WHERE fileid = ?', [$now, $fileIdForShutdown]);
							// DB Fallback erfolgreich: kein Log
						} catch (\Throwable $e2) {
							$log('ERROR: DB fallback failed: ' . $e2->getMessage());
						}
					}
				}
			});
		} catch (\Throwable $e) {
			$this->writeFileLog('ERROR: failed to schedule shutdown touch: ' . $e->getMessage());
		}

		// immediate attempt as well (best-effort)
		try {
			$node->touch($now);
			// Erfolg: kein Log
		} catch (\Throwable $e) {
			$this->writeFileLog('ERROR: touch failed (immediate) for ' . $path . ': ' . $e->getMessage());
			// DB fallback immediate
			if ($fileId) {
				try {
					$db = \OC::$server->getDatabaseConnection();
					$db->executeStatement('UPDATE oc_filecache SET mtime = ? WHERE fileid = ?', [$now, $fileId]);
					// DB Fallback erfolgreich: kein Log
				} catch (\Throwable $e2) {
					$this->writeFileLog('ERROR: DB fallback failed: ' . $e2->getMessage());
				}
			}
		}
	}
}
