<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Card;
use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\CardService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
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

	#[NoAdminRequired]
	public function read(int $cardId): Card {
		return $this->cardService->find($cardId);
	}

	/**
	 * @return Card[]
	 */
	#[NoAdminRequired]
	public function reorder(int $cardId, int $stackId, int $order): array {
		return $this->cardService->reorder($cardId, $stackId, $order);
	}

	#[NoAdminRequired]
	public function rename(int $cardId, string $title): Card {
		return $this->cardService->rename($cardId, $title);
	}

	#[NoAdminRequired]
	public function create(string $title, int $stackId, string $type = 'plain', int $order = 999, string $description = '', $duedate = null, array $labels = [], array $users = []): Card {
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
	 * @param $duedate
	 */
	#[NoAdminRequired]
	public function update(int $id, string $title, int $stackId, string $type, int $order, string $description, $duedate, $deletedAt): Card {
		return $this->cardService->update($id, $title, $stackId, $type, $this->userId, $description, $order, $duedate, $deletedAt);
	}

	#[NoAdminRequired]
	public function clone(int $cardId, ?int $targetStackId = null): Card {
		return $this->cardService->cloneCard($cardId, $targetStackId);
	}

	#[NoAdminRequired]
	public function delete(int $cardId): Card {
		return $this->cardService->delete($cardId);
	}

	/**
	 * @return Card[]
	 */
	#[NoAdminRequired]
	public function deleted(int $boardId): array {
		return $this->cardService->fetchDeleted($boardId);
	}

	#[NoAdminRequired]
	public function archive($cardId) {
		return $this->cardService->archive($cardId);
	}

	#[NoAdminRequired]
	public function unarchive(int $cardId): Card {
		return $this->cardService->unarchive($cardId);
	}

	#[NoAdminRequired]
	public function done(int $cardId): Card {
		return $this->cardService->done($cardId);
	}

	#[NoAdminRequired]
	public function undone(int $cardId): Card {
		return $this->cardService->undone($cardId);
	}

	#[NoAdminRequired]
	public function assignLabel(int $cardId, int $labelId): void {
		$this->cardService->assignLabel($cardId, $labelId);
	}

	#[NoAdminRequired]
	public function removeLabel(int $cardId, int $labelId): void {
		$this->cardService->removeLabel($cardId, $labelId);
	}

	#[NoAdminRequired]
	public function assignUser(int $cardId, string $userId, int $type = 0): Assignment {
		return $this->assignmentService->assignUser($cardId, $userId, $type);
	}

	#[NoAdminRequired]
	public function unassignUser(int $cardId, string $userId, int $type = 0): Assignment {
		return $this->assignmentService->unassignUser($cardId, $userId, $type);
	}
}
