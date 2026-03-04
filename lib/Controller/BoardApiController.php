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
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

use function Sabre\HTTP\parseDate;

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
	 * Return all the boards that the current user has access to.
	 * @throws StatusException
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function index(bool $details = false): DataResponse {
		$modified = $this->request->getHeader('If-Modified-Since');
		if ($modified === '') {
			$boards = $this->boardService->findAll(0, $details === true);
		} else {
			$date = parseDate($modified);
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
	 * Return the board specified by $this->request->getParam('boardId').
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function get(): DataResponse {
		$board = $this->boardService->find($this->request->getParam('boardId'));
		$response = new DataResponse($board, HTTP::STATUS_OK);
		$response->setETag($board->getEtag());
		return $response;
	}

	/**
	 * Create a board with the specified title and color.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function create(string $title, string $color): DataResponse {
		$board = $this->boardService->create($title, $this->userId, $color);
		return new DataResponse($board, HTTP::STATUS_OK);
	}

	/**
	 * Update a board with the specified boardId, title and color, and archived state.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function update(string $title, string $color, bool $archived = false): DataResponse {
		$board = $this->boardService->update($this->request->getParam('boardId'), $title, $color, $archived);
		return new DataResponse($board, HTTP::STATUS_OK);
	}

	/**
	 * Delete the board specified by $boardId.  Return the board that was deleted.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function delete(): DataResponse {
		$board = $this->boardService->delete($this->request->getParam('boardId'));
		return new DataResponse($board, HTTP::STATUS_OK);
	}

	/**
	 * Undo the deletion of the board specified by $boardId.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function undoDelete(): DataResponse {
		$board = $this->boardService->deleteUndo($this->request->getParam('boardId'));
		return new DataResponse($board, HTTP::STATUS_OK);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function addAcl(int $boardId, $type, $participant, $permissionEdit, $permissionShare, $permissionManage) {
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
