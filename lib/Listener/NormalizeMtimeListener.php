<?php
declare(strict_types=1);

namespace OCA\RetentionNormalizeMtime\Listener;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

/** @implements IEventListener<Event> */
class NormalizeMtimeListener implements IEventListener {
	public function __construct(
		private IRootFolder $rootFolder,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
		private IConfig $config,
		private IDBConnection $db,
	) {}

	private function logError(string $message, array $context = []): void {
		$this->logger->warning($message, ['app' => 'retention-normalize-mtime'] + $context);
	}

	public function handle(Event $event): void {
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
			$this->logError('Failed to get node path', ['exception' => $e]);
			return;
		}

		$owner = $node->getOwner();
		if ($owner === null) {
			$this->logError('Owner is null, skipping mtime normalization', ['path' => $path]);
			return;
		}
		$uid = $owner->getUID();

		$limitToGroup = $this->config->getAppValue('retention-normalize-mtime', 'limit_to_group', '');
		$limitToPrefix = $this->config->getAppValue('retention-normalize-mtime', 'limit_to_prefix', '');

		if ($limitToGroup && !$this->groupManager->isInGroup($uid, $limitToGroup)) {
			return;
		}

		if ($limitToPrefix) {
			try {
				$rel = '/' . ltrim($this->getUserRelativePath($uid, $path), '/');
				$prefix = '/' . trim($limitToPrefix, '/');
				if (!str_starts_with($rel, $prefix)) {
					return;
				}
			} catch (\Throwable $e) {
				$this->logError('Failed to resolve relative path', ['path' => $path, 'exception' => $e]);
				return;
			}
		}

		$now = time();
		$fileId = null;
		try {
			$fileId = $node->getId();
		} catch (\Throwable $e) {
			$this->logError('Failed to get file id', ['path' => $path, 'exception' => $e]);
		}

		try {
			register_shutdown_function(function () use ($uid, $path, $now, $fileId): void {
				$this->touchByUserPath($uid, $path, $now, $fileId);
			});
		} catch (\Throwable $e) {
			$this->logError('Failed to schedule shutdown mtime normalization', ['path' => $path, 'exception' => $e]);
		}

		try {
			$node->touch($now);
		} catch (\Throwable $e) {
			$this->logError('Immediate mtime normalization failed', ['path' => $path, 'exception' => $e]);
			$this->updateFileCacheMtime($fileId, $now);
		}
	}

	private function touchByUserPath(string $uid, string $path, int $mtime, ?int $fileId): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			$userFolder->get($this->getUserRelativePath($uid, $path))->touch($mtime);
		} catch (\Throwable $e) {
			$this->logError('Shutdown mtime normalization failed', ['path' => $path, 'exception' => $e]);
			$this->updateFileCacheMtime($fileId, $mtime);
		}
	}

	private function getUserRelativePath(string $uid, string $path): string {
		$trimmed = ltrim($path, '/');
		$prefix = $uid . '/files/';
		if (str_starts_with($trimmed, $prefix)) {
			return substr($trimmed, strlen($prefix));
		}

		if ($trimmed === $uid . '/files') {
			return '';
		}

		if (str_starts_with($trimmed, 'files/')) {
			return substr($trimmed, strlen('files/'));
		}

		return ltrim((string)$this->rootFolder->getUserFolder($uid)->getRelativePath($path), '/');
	}

	private function updateFileCacheMtime(?int $fileId, int $mtime): void {
		if ($fileId === null || $fileId <= 0) {
			return;
		}

		try {
			$query = $this->db->getQueryBuilder();
			$query->update('filecache')
				->set('mtime', $query->createNamedParameter($mtime, IQueryBuilder::PARAM_INT))
				->where($query->expr()->eq('fileid', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
			$query->executeStatement();
		} catch (\Throwable $e) {
			$this->logError('Filecache mtime fallback failed', ['fileId' => $fileId, 'exception' => $e]);
		}
	}
}
