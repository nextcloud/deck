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

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;

use OCA\Deck\Service\BoardService;

/**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class BoardApiController extends ApiController {

	private $service;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param BoardService $service
	 * @param $userId
	 */
	public function __construct($appName, IRequest $request, BoardService $service, $userId) {
		parent::__construct($appName, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Return all of the boards that the current user has access to.
	 */
	public function index() {
		$boards = $this->service->findAll();

		return new DataResponse($boards, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 *
	 * Return the board specified by $this->request->params['boardId'].
	 */
	public function get() {				

		if (is_numeric($this->request->getParam('boardId')) === false) {
			return new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		$board = $this->service->find($this->request->getParam('boardId'));

		if ($board === false || $board === null) {
			return new DataResponse('board not found', HTTP::STATUS_NOT_FOUND);
		}

		return new DataResponse($board, HTTP::STATUS_OK);
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

		if ($title === false) {
			return new DataResponse('title must be provided', HTTP::STATUS_BAD_REQUEST);
		}

		if ($color === false) {
			return new DataResponse('color must be provided', HTTP::STATUS_BAD_REQUEST);
		}

		$board = $this->service->create($title, $this->userId, $color);

		if ($board === false || $board === null) {
			return new DataResponse('Internal Server Error', HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}

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

		if (is_numeric($this->request->params['boardId']) === false) {
			return new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		if (is_bool($archived) === false) {
			return new DataResponse('archived must be a boolean', HTTP::STATUS_BAD_REQUEST);
		}

		if ($title === false) {
			return new DataResponse('title must be provided', HTTP::STATUS_BAD_REQUEST);
		}

		if ($color === false) {
			return new DataResponse('color must be provided', HTTP::STATUS_BAD_REQUEST);
		}

		$board = $this->service->update($this->request->params['boardId'], $title, $color, $archived);

		if ($board === false || $board === null) {
			return new DataResponse('Board not found', HTTP::STATUS_NOT_FOUND);
		}

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

		if (is_numeric($this->request->params['boardId']) === false) {
			return new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		$board = $this->service->delete($this->request->params['boardId']);

		if ($board === false || $board === null) {
			return new DataResponse('Board not found', HTTP::STATUS_NOT_FOUND);
		}

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

		if (is_numeric($this->request->params['boardId']) === false) {
			return new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		$board = $this->service->find($this->request->params['boardId']);

		if ($board === false || $board === null) {
			return new DataResponse('Board not found', HTTP::STATUS_NOT_FOUND);
		} else {
			$board = $this->service->deleteUndo($id);
		}

		return new DataResponse($board, HTTP::STATUS_OK);
	}

}
