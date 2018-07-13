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

 use OCA\Deck\Service\CardService;

 /**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class CardApiController extends ApiController {
	private $cardService;
	private $userId;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param BoardService $service
	 * @param $userId
	 */
	public function __construct($appName, IRequest $request, CardService $cardService, $userId) {
		parent::__construct($appName, $request);
		$this->cardService = $cardService;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Get a specific card.
	 */
	public function get() {		

		if (is_numeric($this->request->params['boardId']) === false) {
			return new DataResponse("board id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($this->request->params['stackId']) === false) {
			return new DataResponse("stack id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($this->request->params['cardId']) === false) {
			return new DataResponse("card id must be a number", HTTP::STATUS_BAD_REQUEST);
		}		

		$card = $this->cardService->find($this->request->params['cardId']);

		if ($card === false || $card === null) {
			return new DataResponse('Card not found', HTTP::STATUS_NOT_FOUND);
		}

		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $type
	 * @params $order
	 * 
	 * Get a specific card.
	 */
	public function create($title, $type = 'plain', $order = 999) {

		if (is_numeric($this->request->params['boardId']) === false) {
			return new DataResponse("board id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($this->request->params['stackId']) === false) {
			return new DataResponse("stack id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if ($title === false || $title === null) {
			return new DataResponse("title must be provided", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($order) === false) {
			return new DataResponse("order must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		try {
			$card = $this->cardService->create($title, $this->request->params['stackId'], $type, $order, $this->userId);
		} catch (Exception $e) {
			return new DataResponse($e->getMessage(), HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}
		
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $type
	 * @params $order
	 * @params $description
	 * @params $duedate
	 * 
	 * Get a specific card.
	 */
	public function update($title, $type, $order, $description = null, $duedate = null) {

		if (is_numeric($this->request->params['cardId']) === false) {
			return new DataResponse("card id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($this->request->params['stackId']) === false) {
			return new DataResponse("stack id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($this->request->params['boardId']) === false) {
			return new DataResponse("board id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if ($title === false || $title === null) {
			return new DataResponse("title must be provided", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($order) === false) {
			return new DataResponse("order must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		try {
			$card = $this->cardService->update(
				$this->request->params['cardId'],
				$title,
				$this->request->params['stackId'],
				$type,
				$order,
				$description,
				$this->userId,
				$duedate);
		} catch(Exception $e) {
			return new DataResponse($e->getMessage(), HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse($card, HTTP::STATUS_OK);
	}	

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired	 	 
	 * 
	 * Delete a specific card.
	 */
	public function delete() {

		if (is_numeric($this->request->params['cardId']) === false) {
			return new DataResponse("card id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($this->request->params['stackId']) === false) {
			return new DataResponse("stack id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($this->request->params['boardId']) === false) {
			return new DataResponse("board id must be a number", HTTP::STATUS_BAD_REQUEST);
		}

		try {
			$card = $this->cardService->delete($this->request->params['cardId']);
		} catch (Exception $e) {
			return new DataResponse($e.getMessage(), HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}		

		return new DataResponse($card, HTTP::STATUS_OK);
	}
}