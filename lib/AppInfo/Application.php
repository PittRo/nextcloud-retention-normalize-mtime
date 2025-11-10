<?php
namespace OCA\RetentionNormalizeMtime\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCA\RetentionNormalizeMtime\Listener\NormalizeMtimeListener;
use OCA\RetentionNormalizeMtime\Settings\AdminSettings;
use Psr\Container\ContainerInterface;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCP\IServerContainer;

// Guard gegen doppelte Deklaration, falls die Klasse irgendwo anders schon geladen wurde
if (!\class_exists('OCA\\RetentionNormalizeMtime\\AppInfo\\Application', false)) {
	class Application extends App implements IBootstrap {
		public const APP_ID = 'nextcloud-retention-normalize-mtime';
		public function __construct() { parent::__construct(self::APP_ID); }

		public function register(IRegistrationContext $context): void {
			// Register admin settings
			$context->registerSetting(AdminSettings::class);

			// Register listener as a service so DI is reliable
			$context->registerService(NormalizeMtimeListener::class, function (ContainerInterface $c) {
				// get server container if available
				if ($c->has(IServerContainer::class)) {
					$server = $c->get(IServerContainer::class);
					$root = $server->get(IRootFolder::class);
					$group = $server->get(IGroupManager::class);
					$logger = $server->get(LoggerInterface::class);
					$config = $server->get(IConfig::class);
					return new NormalizeMtimeListener($root, $group, $logger, $config);
				}
				// fallback: try container directly
				$root = $c->get(IRootFolder::class);
				$group = $c->get(IGroupManager::class);
				$logger = $c->get(LoggerInterface::class);
				$config = $c->get(IConfig::class);
				return new NormalizeMtimeListener($root, $group, $logger, $config);
			});

			// Register the event listener using the service id (class name)
			$context->registerEventListener(NodeCreatedEvent::class, NormalizeMtimeListener::class);
			$context->registerEventListener(NodeWrittenEvent::class, NormalizeMtimeListener::class);
		}

		public function boot(IBootContext $context): void {
			// Boot phase - nothing to do here
		}
	}
}
