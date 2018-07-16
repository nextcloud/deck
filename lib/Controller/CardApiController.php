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

 use OCA\Deck\Controller\Helper\ApiHelper;
 use OCA\Deck\Service\BoardService;
 use OCA\Deck\Service\StackService;
 use OCA\Deck\Service\CardService;

 /**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class CardApiController extends ApiController {
	private $cardService;
	private $boardService;
	private $stackService;
	private $userId;
	private $apiHelper;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param CardService $service
	 * @param $userId
	 */
	public function __construct($appName, IRequest $request, CardService $cardService, BoardService $boardService, StackService $stackService, $userId) {
		parent::__construct($appName, $request);
		$this->boardService = $boardService;
		$this->cardService = $cardService;
		$this->stackService = $stackService;
		$this->userId = $userId;
		$this->apiHelper = new ApiHelper();
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Get a specific card.
	 */
	public function get() {		
		$card = $this->cardService->find($this->request->params['cardId']);		
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
		$card = $this->cardService->create($title, $this->request->params['stackId'], $type, $order, $this->userId);
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *	 
	 * 
	 * Update a card
	 */
	public function update($cardId, $title, $stackId, $type, $order = 0, $description = '', $owner, $duedate = null) {
		$card = $this->cardService->update($this->request->getParam('cardId'), $title, $this->request->getParam('stackId'), $type, $order, $description, $owner, $duedate);
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	
	public function assignUser() {
		throw new Exception('Not Implemented');
	}

	public function unassignUser() {
		throw new Exception('Not Implemented');
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired	 	 
	 * 
	 * Delete a specific card.
	 */
	public function delete() {
		$card = $this->cardService->delete($this->request->params['cardId']);
		return new DataResponse($card, HTTP::STATUS_OK);
	}
}