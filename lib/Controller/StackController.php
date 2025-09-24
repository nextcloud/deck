<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Stack;
use OCA\Deck\Service\StackService;

use OCP\AppFramework\Controller;

use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;

class StackController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private StackService $stackService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return Stack[]
	 */
	#[NoAdminRequired]
	public function index(int $boardId): array {
		return $this->stackService->findAll($boardId);
	}

	/**
	 * @return Stack[]
	 */
	#[NoAdminRequired]
	public function archived(int $boardId): array {
		return $this->stackService->findAllArchived($boardId);
	}

	#[NoAdminRequired]
	public function create(string $title, int $boardId, int $order = 999): Stack {
		return $this->stackService->create($title, $boardId, $order);
	}

	#[NoAdminRequired]
	public function update(int $id, string $title, int $boardId, int $order, ?int $deletedAt = null): Stack {
		return $this->stackService->update($id, $title, $boardId, $order, $deletedAt);
	}

	/**
	 * @return array<int, Stack>
	 */
	#[NoAdminRequired]
	public function reorder(int $stackId, int $order): array {
		return $this->stackService->reorder($stackId, $order);
	}

	#[NoAdminRequired]
	public function delete(int $stackId): Stack {
		return $this->stackService->delete($stackId);
	}

	/**
	 * @return Stack[]
	 */
	#[NoAdminRequired]
	public function deleted(int $boardId): array {
		return $this->stackService->fetchDeleted($boardId);
	}
}
