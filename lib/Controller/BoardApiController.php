<?php
/**
 * @copyright Copyright (c) 2017 Steven R. Baker <steven@stevenrbaker.com>
 *
 * @author Steven R. Baker <steven@stevenrbaker.com>
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Board;
use OCA\Deck\StatusException;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

use OCA\Deck\Service\BoardService;
use Sabre\HTTP\Util;

/**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class BoardApiController extends ApiController {
	private $boardService;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param BoardService $service
	 * @param $userId
	 */
	public function __construct($appName, IRequest $request, BoardService $service, $userId) {
		parent::__construct($appName, $request);
		$this->boardService = $service;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Return all of the boards that the current user has access to.
	 * @throws StatusException
	 */
	public function index($details = null) {
		$modified = $this->request->getHeader('If-Modified-Since');
		if ($modified === null || $modified === '') {
			$boards = $this->boardService->findAll(0, $details);
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
}
