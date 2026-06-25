<?php
declare(strict_types=1);

namespace OCA\RetentionNormalizeMtime\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCA\RetentionNormalizeMtime\Listener\NormalizeMtimeListener;

class Application extends App implements IBootstrap {
	public const APP_ID = 'retention-normalize-mtime';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(NodeCreatedEvent::class, NormalizeMtimeListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, NormalizeMtimeListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
