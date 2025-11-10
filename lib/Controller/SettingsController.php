<?php
namespace OCA\RetentionNormalizeMtime\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\RetentionNormalizeMtime\Settings\AdminSettings)
	 */
	public function setAdminConfig(string $key, string $value): JSONResponse {
		$allowedKeys = ['limit_to_group', 'limit_to_prefix'];

		if (!in_array($key, $allowedKeys)) {
			return new JSONResponse(['status' => 'error', 'message' => 'Invalid key'], 400);
		}

		$this->config->setAppValue('retention_normalize_mtime', $key, $value);

		return new JSONResponse(['status' => 'success']);
	}
}

