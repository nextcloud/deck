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
	 * @param CardService $cardService
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
		$card = $this->cardService->find($this->request->getParam('cardId'));
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
		$card = $this->cardService->create($title, $this->request->getParam('stackId'), $type, $order, $this->userId);
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
	public function update($title, $type, $order = 0, $description = '', $owner, $duedate = null, $archived = null) {
		$card = $this->cardService->update($this->request->getParam('cardId'), $title, $this->request->getParam('stackId'), $type, $order, $description, $owner, $duedate, 0, $archived);
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
		$card = $this->cardService->delete($this->request->getParam('cardId'));
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Assign a label to a card.
	 */
	public function assignLabel($labelId) {
		$card = $this->cardService->assignLabel($this->request->getParam('cardId'), $labelId);
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Assign a label to a card.
	 */
	public function removeLabel($labelId) {
		$card = $this->cardService->removeLabel($this->request->getParam('cardId'), $labelId);
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Unassign a user from a card
	 */
	public function unassignUser($userId) {
		$card = $this->cardService->unassignUser($this->request->getParam('cardId'), $userId);
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Assign a user to a card
	 */
	public function assignUser($userId) {
		$card = $this->cardService->assignUser($this->request->getParam('cardId'), $userId);;
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Reorder cards
	 */
	public function reorder($stackId, $order) {
		$card = $this->cardService->reorder($this->request->getParam('cardId'), $stackId, $order);
		return new DataResponse($card, HTTP::STATUS_OK);
	}
}
