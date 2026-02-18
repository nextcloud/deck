<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\ExternalBoardService;
use OCA\Deck\Service\StackService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CardOcsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CardService $cardService,
		private StackService $stackService,
		private BoardService $boardService,
		private ExternalBoardService $externalBoardService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function create(string $title, int $stackId, ?int $boardId = null, ?string $type = 'plain', ?string $owner = null, ?int $order = 999, ?string $description = '', $duedate = null, ?array $labels = [], ?array $users = []) {
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
		$card = $this->cardService->create($title, $stackId, $type, $order, $owner, $description, $duedate);

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
	#[NoCSRFRequired]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function update(int $id, string $title, int $stackId, string $type, int $order, string $description, $duedate, $deletedAt, int $boardId, array|string|null $owner = null, $archived = null): DataResponse {
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
			$done
		));
	}
}
