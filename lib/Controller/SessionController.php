<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, chandi Langecker (git@chandi.it)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
	public function __construct($appName,
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
