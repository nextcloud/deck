<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\PermissionService;
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
		private PermissionService $permissionService,
		private BoardMapper $boardMapper,
		private BoardService $boardService,
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
		$boardId = null;
		if (preg_match('/^board:(\d+):/', $key, $matches) === 1) {
			$boardId = (int)$matches[1];
			$this->permissionService->checkPermission(
				$this->boardMapper,
				$boardId,
				Acl::PERMISSION_EDIT,
			);
		}

		// Swimlane settings are board columns. Only a key that passed the
		// numeric EDIT check above may reach BoardService.
		$setting = $boardId === null ? null : substr($key, strlen($matches[0]));
		if ($setting !== null && in_array($setting, BoardService::SWIMLANE_SETTINGS, true)) {
			return new DataResponse($this->boardService->setSwimlaneSetting($boardId, $setting, $value));
		}

		$result = $this->configService->set($key, $value);
		if ($result === null) {
			return new NotFoundResponse();
		}
		return new DataResponse($result);
	}
}
