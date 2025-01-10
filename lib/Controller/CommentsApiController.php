<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\CommentService;
use OCA\Deck\StatusException;
use OCP\AppFramework\Http\DataResponse;

use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CommentsApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CommentService $commentService,
		string $corsMethods = 'PUT, POST, GET, DELETE, PATCH',
		string $corsAllowedHeaders = 'Authorization, Content-Type, Accept',
		int $corsMaxAge = 1728000,
	) {
		parent::__construct($appName, $request, $corsMethods, $corsAllowedHeaders, $corsMaxAge);
	}

	/**
	 * @NoAdminRequired
	 * @throws StatusException
	 */
	public function list(string $cardId, int $limit = 20, int $offset = 0): DataResponse {
		return $this->commentService->list($cardId, $limit, $offset);
	}

	/**
	 * @NoAdminRequired
	 * @throws StatusException
	 */
	public function create(int $cardId, string $message, int $parentId = 0): DataResponse {
		return $this->commentService->create($cardId, $message, $parentId);
	}

	/**
	 * @NoAdminRequired
	 * @throws StatusException
	 */
	public function update(int $cardId, int $commentId, string $message): DataResponse {
		return $this->commentService->update($cardId, $commentId, $message);
	}

	/**
	 * @NoAdminRequired
	 * @throws StatusException
	 */
	public function delete(int $cardId, int $commentId): DataResponse {
		return $this->commentService->delete($cardId, $commentId);
	}
}
