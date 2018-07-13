<?php
/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
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
use OCA\Deck\Service\LabelService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Controller\Helper\ApiHelper;

 /**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class LabelApiController extends ApiController {

	private $labelService;
	private $userId;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param LabelService $service
	 * @param $userId
	 */
	public function __construct($appName, IRequest $request, LabelService $labelService, BoardService $boardService, $userId) {
		parent::__construct($appName, $request);
		$this->labelService = $labelService;
		$this->boardService = $boardService;
		$this->userId = $userId;
		$this->apiHelper = new ApiHelper();
	}
	
	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Get a specific label.
	 */
	public function get() {

		$boardError = $this->apiHelper->entityHasError($this->request->params['boardId'], 'board', $this->boardService);
		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}

		if (is_numeric($this->request->params['labelId']) === false) {
			return new DataResponse('label id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		$label = $this->labelService->find($this->request->params['labelId']);

		if ($label === false || $label === null) {
			return new DataResponse('Label not found', HTTP::STATUS_NOT_FOUND);
		}

		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $color
	 * Create a new label
	 */
	public function create($title, $color) {

		$boardError = $this->apiHelper->entityHasError($this->request->params['boardId'], 'board', $this->boardService);
		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}

		if ($title === false || $title === null) {
			return new DataResponse('title must be provided', HTTP::STATUS_BAD_REQUEST);
		}

		if ($color === false || $color === null) {
			return new DataResponse('color must be provided', HTTP::STATUS_BAD_REQUEST);
		}

		try {
			$label = $this->labelService->create($title, $color, $this->request->params['boardId']);
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}
		
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $color
	 * Update a specific label
	 */
	public function update($title, $color) {

		$boardError = $this->apiHelper->entityHasError($this->request->params['boardId'], 'board', $this->boardService);
		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}

		if (is_numeric($this->request->params['labelId']) === false) {
			return new DataResponse('label id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		if ($title === false || $title === null) {
			return new DataResponse('title must be provided', HTTP::STATUS_BAD_REQUEST);
		}

		if ($color === false || $color === null) {
			return new DataResponse('color must be provided', HTTP::STATUS_BAD_REQUEST);
		}

		try {
			$label = $this->labelService->update($this->request->params['labelId'], $title, $color);
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}
		
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *	 
	 * Delete a specific label
	 */
	public function delete() {

		$boardError = $this->apiHelper->entityHasError($this->request->params['boardId'], 'board', $this->boardService);
		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}

		if (is_numeric($this->request->params['labelId']) === false) {
			return new DataResponse('label id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		try {
			$label = $this->labelService->delete($this->request->params['labelId']);
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}		

		return new DataResponse($label, HTTP::STATUS_OK);
	}
	
}