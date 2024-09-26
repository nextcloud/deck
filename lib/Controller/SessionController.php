<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\SessionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class SessionController extends OCSController {
	public function __construct(
		$appName,
		IRequest $request,
		private SessionService $sessionService,
		private PermissionService $permissionService,
		private BoardMapper $boardMapper,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function create(int $boardId): DataResponse {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);

		$session = $this->sessionService->initSession($boardId);
		return new DataResponse([
			'token' => $session->getToken(),
		]);
	}

	#[NoAdminRequired]
	public function sync(int $boardId, string $token): DataResponse {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		try {
			$this->sessionService->syncSession($boardId, $token);
			return new DataResponse([]);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], 404);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function close(int $boardId, ?string $token = null): DataResponse {
		if ($token === null) {
			return new DataResponse();
		}
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		$this->sessionService->closeSession($boardId, $token);
		return new DataResponse();
	}
}
