<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardViewService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class BoardViewApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private BoardViewService $boardViewService,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(int $boardId): DataResponse {
		return new DataResponse($this->boardViewService->findAll($boardId));
	}

	#[NoAdminRequired]
	public function create(int $boardId, string $name, array $filters): DataResponse {
		return new DataResponse($this->boardViewService->create($boardId, $name, $filters));
	}

	#[NoAdminRequired]
	public function update(int $boardId, int $viewId, string $name, array $filters): DataResponse {
		return new DataResponse($this->boardViewService->update($boardId, $viewId, $name, $filters));
	}

	#[NoAdminRequired]
	public function delete(int $boardId, int $viewId): DataResponse {
		$this->boardViewService->delete($boardId, $viewId);
		return new DataResponse([]);
	}
}
