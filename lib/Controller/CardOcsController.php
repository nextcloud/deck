<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\ExternalBoardService;
use OCA\Deck\Service\StackService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CardOcsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CardService $cardService,
		private AssignmentService $assignmentService,
		private StackService $stackService,
		private BoardService $boardService,
		private ExternalBoardService $externalBoardService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function create(string $title, int $stackId, ?int $boardId = null, ?string $type = 'plain', ?string $owner = null, ?int $order = 999, ?string $description = '', $duedate = null, $startdate = null, ?array $labels = [], ?array $users = []) {
		if ($boardId) {
			$board = $this->boardService->find($boardId, false);
			if ($board->getExternalId()) {
				$card = $this->externalBoardService->createCardOnRemote($board, $title, $stackId, $type, $order, $description, $duedate, $users);
				return new DataResponse($card);
			}
		}

		if (!$owner) {
			$owner = $this->userId;
		}
		$card = $this->cardService->create($title, $stackId, $type, $order, $owner, $description, $duedate, $startdate);

		// foreach ($labels as $label) {
		// 	$this->assignLabel($card->getId(), $label);
		// }

		// foreach ($users as $user) {
		// 	$this->assignmentService->assignUser($card->getId(), $user['id'], $user['type']);
		// }

		return new DataResponse($card);
	}


	#[NoAdminRequired]
	#[PublicPage]
	public function assignLabel(?int $boardId, int $cardId, int $labelId): DataResponse {
		if ($boardId) {
			$board = $this->boardService->find($boardId, false);
			if ($board->getExternalId()) {
				return new DataResponse($this->externalBoardService->assignLabelOnRemote($board, $cardId, $labelId));
			}
		}

		return new DataResponse($this->cardService->assignLabel($cardId, $labelId));
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function assignUser(?int $boardId, int $cardId, string $userId, int $type = 0): DataResponse {
		if ($boardId) {
			$localBoard = $this->boardService->find($boardId, false);
			if ($localBoard->getExternalId()) {
				return new DataResponse($this->externalBoardService->assignUserOnRemote($localBoard, $cardId, $userId, $type));
			}
		}
		return new DataResponse($this->assignmentService->assignUser($cardId, $userId, $type));
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function unAssignUser(?int $boardId, int $cardId, string $userId, int $type = 0): DataResponse {
		if ($boardId) {
			$localBoard = $this->boardService->find($boardId, false);
			if ($localBoard->getExternalId()) {
				return new DataResponse($this->externalBoardService->unAssignUserOnRemote($localBoard, $cardId, $userId, $type));
			}
		}
		return new DataResponse($this->assignmentService->unAssignUser($cardId, $userId, $type));
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function removeLabel(?int $boardId, int $cardId, int $labelId): DataResponse {
		if ($boardId) {
			$board = $this->boardService->find($boardId, false);
			if ($board->getExternalId()) {
				return new DataResponse($this->externalBoardService->removeLabelOnRemote($board, $cardId, $labelId));
			}
		}

		return new DataResponse($this->cardService->removeLabel($cardId, $labelId));
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function update(int $id, string $title, int $stackId, string $type, int $order, string $description, $duedate, $deletedAt, int $boardId, array|string|null $owner = null, $archived = null, $startdate = null): DataResponse {
		$done = array_key_exists('done', $this->request->getParams())
			? new OptionalNullableValue($this->request->getParam('done', null))
			: null;
		if (!$owner) {
			$owner = $this->userId;
		} else {
			if (!is_string($owner)) {
				$owner = $owner['uid'];
			}
		}

		$localBoard = $this->boardService->find($boardId, false);
		if ($localBoard->getExternalId()) {
			return new DataResponse($this->externalBoardService->updateCardOnRemote(
				$localBoard,
				$id,
				$title,
				$stackId,
				$type,
				$owner,
				$description,
				$order,
				$duedate,
				$deletedAt,
				$archived,
				$done
			));
		}

		return new DataResponse($this->cardService->update($id,
			$title,
			$stackId,
			$type,
			$owner,
			$description,
			$order,
			$duedate,
			$deletedAt,
			$archived,
			$done,
			$startdate
		));
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function reorder(int $cardId, int $stackId, int $order, ?int $boardId): DataResponse {
		if ($boardId) {
			$board = $this->boardService->find($boardId, false);
			if ($board->getExternalId()) {
				return new DataResponse($this->externalBoardService->reorderCardOnRemote($board, $cardId, $stackId, $order));
			}
		}
		return new DataResponse($this->cardService->reorder($cardId, $stackId, $order));
	}
}
