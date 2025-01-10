<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\StackService;

use OCP\AppFramework\Controller;

use OCP\IRequest;

class StackController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private StackService $stackService,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return array
	 */
	public function index($boardId) {
		return $this->stackService->findAll($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return array
	 */
	public function archived($boardId) {
		return $this->stackService->findAllArchived($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $title
	 * @param $boardId
	 * @param int $order
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function create($title, $boardId, $order = 999) {
		return $this->stackService->create($title, $boardId, $order);
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $title
	 * @param $boardId
	 * @param $order
	 * @param $deletedAt
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function update($id, $title, $boardId, $order, $deletedAt) {
		return $this->stackService->update($id, $title, $boardId, $order, $deletedAt);
	}

	/**
	 * @NoAdminRequired
	 * @param $stackId
	 * @param $order
	 * @return array
	 */
	public function reorder($stackId, $order) {
		return $this->stackService->reorder((int)$stackId, (int)$order);
	}

	/**
	 * @NoAdminRequired
	 * @param $stackId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function delete($stackId) {
		return $this->stackService->delete($stackId);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function deleted($boardId) {
		return $this->stackService->fetchDeleted($boardId);
	}
}
