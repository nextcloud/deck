<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Board;
use OCA\Deck\Service\BoardService;
use OCA\Deck\StatusException;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCP\IRequest;
use Sabre\HTTP\Util;

/**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class BoardApiController extends ApiController {
	/**
	 * @param string $appName
	 */
	public function __construct(
		$appName,
		IRequest $request,
		private BoardService $boardService,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Return all of the boards that the current user has access to.
	 *
	 * @param bool $details
	 * @throws StatusException
	 */
	public function index(bool $details = false) {
		$modified = $this->request->getHeader('If-Modified-Since');
		if ($modified === null || $modified === '') {
			$boards = $this->boardService->findAll(0, $details === true);
		} else {
			$date = Util::parseHTTPDate($modified);
			if (!$date) {
				throw new StatusException('Invalid If-Modified-Since header provided.');
			}
			$boards = $this->boardService->findAll($date->getTimestamp(), $details);
		}
		$response = new DataResponse($boards, HTTP::STATUS_OK);
		$response->setETag(md5(json_encode(array_map(function (Board $board) {
			return $board->getId() . '-' . $board->getETag();
		}, $boards))));
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 *
	 * Return the board specified by $this->request->getParam('boardId').
	 */
	public function get() {
		$board = $this->boardService->find($this->request->getParam('boardId'));
		$response = new DataResponse($board, HTTP::STATUS_OK);
		$response->setETag($board->getEtag());
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $color
	 *
	 * Create a board with the specified title and color.
	 */
	public function create($title, $color) {
		$board = $this->boardService->create($title, $this->userId, $color);
		return new DataResponse($board, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $color
	 * @params $archived
	 *
	 * Update a board with the specified boardId, title and color, and archived state.
	 */
	public function update($title, $color, $archived = false) {
		$board = $this->boardService->update($this->request->getParam('boardId'), $title, $color, $archived);
		return new DataResponse($board, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 *
	 * Delete the board specified by $boardId.  Return the board that was deleted.
	 */
	public function delete() {
		$board = $this->boardService->delete($this->request->getParam('boardId'));
		return new DataResponse($board, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 *
	 * Undo the deletion of the board specified by $boardId.
	 */
	public function undoDelete() {
		$board = $this->boardService->deleteUndo($this->request->getParam('boardId'));
		return new DataResponse($board, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function addAcl($boardId, $type, $participant, $permissionEdit, $permissionShare, $permissionManage) {
		$acl = $this->boardService->addAcl($boardId, $type, $participant, $permissionEdit, $permissionShare, $permissionManage);
		return new DataResponse($acl, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function updateAcl($aclId, $permissionEdit, $permissionShare, $permissionManage) {
		$acl = $this->boardService->updateAcl($aclId, $permissionEdit, $permissionShare, $permissionManage);
		return new DataResponse($acl, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteAcl($aclId) {
		$acl = $this->boardService->deleteAcl($aclId);
		return new DataResponse($acl, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 */
	public function clone(int $boardId, bool $withCards = false, bool $withAssignments = false, bool $withLabels = false, bool $withDueDate = false, bool $moveCardsToLeftStack = false, bool $restoreArchivedCards = false): DataResponse {
		return new DataResponse(
			$this->boardService->clone($boardId, $this->userId, $withCards, $withAssignments, $withLabels, $withDueDate, $moveCardsToLeftStack, $restoreArchivedCards)
		);
	}
}
