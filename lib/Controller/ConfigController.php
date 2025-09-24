<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\ConfigService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class ConfigController extends OCSController {
	public function __construct(
		$AppName,
		IRequest $request,
		private ConfigService $configService,
	) {
		parent::__construct($AppName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function get(): DataResponse {
		return new DataResponse($this->configService->getAll());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function setValue(string $key, mixed $value): DataResponse|NotFoundResponse {
		$result = $this->configService->set($key, $value);
		if ($result === null) {
			return new NotFoundResponse();
		}
		return new DataResponse($result);
	}
}
