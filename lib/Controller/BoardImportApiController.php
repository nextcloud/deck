<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\Importer\BoardImportService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class BoardImportApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private BoardImportService $boardImportService,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function import(string $system, array $config, array $data): DataResponse {
		$this->boardImportService->setSystem($system);
		$config = json_decode(json_encode($config));
		$config->owner = $this->userId;
		$this->boardImportService->setConfigInstance($config);
		$this->boardImportService->setData(json_decode(json_encode($data)));
		$this->boardImportService->import();
		return new DataResponse($this->boardImportService->getBoard(), Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getAllowedSystems(): DataResponse {
		$allowedSystems = $this->boardImportService->getAllowedImportSystems();
		return new DataResponse($allowedSystems, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getConfigSchema(string $name): DataResponse {
		$this->boardImportService->setSystem($name);
		$this->boardImportService->validateSystem();
		$jsonSchemaPath = json_decode(file_get_contents($this->boardImportService->getJsonSchemaPath()));
		return new DataResponse($jsonSchemaPath, Http::STATUS_OK);
	}
}
