<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CommentService;
use OCA\Deck\Service\ExternalBoardService;
use OCA\Deck\StatusException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-api
 */
class CommentsApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CommentService $commentService,
		private BoardService $boardService,
		private ExternalBoardService $externalBoardService,
		private ?string $userId,
		string $corsMethods = 'PUT, POST, GET, DELETE, PATCH',
		string $corsAllowedHeaders = 'Authorization, Content-Type, Accept',
		int $corsMaxAge = 1728000,
	) {
		parent::__construct($appName, $request, $corsMethods, $corsAllowedHeaders, $corsMaxAge);
	}

	/**
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	#[PublicPage]
	public function list(int $cardId, int $limit = 20, int $offset = 0, ?int $boardId = null): DataResponse {
		if ($boardId) {
			$board = $this->boardService->find($boardId, false);
			if ($board->getExternalId()) {
				return new DataResponse($this->externalBoardService->getCardCommentsFromRemote($board, $cardId, $limit, $offset));
			}
		}
		return $this->commentService->list($cardId, $limit, $offset);
	}

	/**
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	#[PublicPage]
	public function create(int $cardId, string $message, int $parentId = 0, ?int $boardId = null): DataResponse {
		if ($boardId) {
			$board = $this->boardService->find($boardId, false);
			if ($board->getExternalId()) {
				return new DataResponse($this->externalBoardService->createCardCommentOnRemote($board, $cardId, $message, $parentId));
			}
		}

		return $this->commentService->create($cardId, $message, $parentId);
	}

	/**
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	public function update(int $cardId, int $commentId, string $message): DataResponse {
		return $this->commentService->update($cardId, $commentId, $message);
	}

	/**
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	public function delete(int $cardId, int $commentId): DataResponse {
		return $this->commentService->delete($cardId, $commentId);
	}
}
