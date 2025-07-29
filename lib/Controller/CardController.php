<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\CardService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class CardController extends Controller {
	public function __construct(
		$appName,
		IRequest $request,
		private CardService $cardService,
		private AssignmentService $assignmentService,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function read($cardId) {
		return $this->cardService->find($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $stackId
	 * @param $order
	 * @return array
	 */
	public function reorder($cardId, $stackId, $order) {
		return $this->cardService->reorder((int)$cardId, (int)$stackId, (int)$order);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $title
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function rename($cardId, $title) {
		return $this->cardService->rename($cardId, $title);
	}

	/**
	 * @NoAdminRequired
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param int $order
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function create($title, $stackId, $type = 'plain', $order = 999, string $description = '', $duedate = null, $labels = [], $users = []) {
		$card = $this->cardService->create($title, $stackId, $type, $order, $this->userId, $description, $duedate);

		foreach ($labels as $label) {
			$this->assignLabel($card->getId(), $label);
		}

		foreach ($users as $user) {
			$this->assignmentService->assignUser($card->getId(), $user['id'], $user['type']);
		}

		return $card;
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param $order
	 * @param $description
	 * @param $duedate
	 * @param $deletedAt
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function update($id, $title, $stackId, $type, $order, $description, $duedate, $deletedAt) {
		return $this->cardService->update($id, $title, $stackId, $type, $this->userId, $description, $order, $duedate, $deletedAt);
	}
	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $targetStackId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function clone(int $cardId, ?int $targetStackId = null) {
		return $this->cardService->cloneCard($cardId, $targetStackId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function delete($cardId) {
		return $this->cardService->delete($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function deleted($boardId) {
		return $this->cardService->fetchDeleted($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function archive($cardId) {
		return $this->cardService->archive($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function unarchive($cardId) {
		return $this->cardService->unarchive($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function done(int $cardId) {
		return $this->cardService->done($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function undone(int $cardId) {
		return $this->cardService->undone($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $labelId
	 */
	public function assignLabel($cardId, $labelId) {
		$this->cardService->assignLabel($cardId, $labelId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $labelId
	 */
	public function removeLabel($cardId, $labelId) {
		$this->cardService->removeLabel($cardId, $labelId);
	}

	/**
	 * @NoAdminRequired
	 */
	public function assignUser($cardId, $userId, $type = 0) {
		return $this->assignmentService->assignUser($cardId, $userId, $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function unassignUser($cardId, $userId, $type = 0) {
		return $this->assignmentService->unassignUser($cardId, $userId, $type);
	}
}
