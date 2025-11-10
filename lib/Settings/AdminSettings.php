<?php
namespace OCA\RetentionNormalizeMtime\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
	public function __construct(
		private IConfig $config
	) {}

	public function getForm(): TemplateResponse {
		$parameters = [
			'limit_to_group' => $this->config->getAppValue('retention_normalize_mtime', 'limit_to_group', ''),
			'limit_to_prefix' => $this->config->getAppValue('retention_normalize_mtime', 'limit_to_prefix', ''),
		];

		return new TemplateResponse('retention_normalize_mtime', 'admin', $parameters);
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 50;
	}
}

