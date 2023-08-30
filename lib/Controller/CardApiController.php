<?php
/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @copyright Copyright (c) 2019, Alexandru Puiu (alexpuiu20@yahoo.com)
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

use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\CardService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class CardApiController extends ApiController {

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param CardService $cardService
	 * @param AssignmentService $assignmentService
	 * @param $userId
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private CardService $cardService,
		private AssignmentService $assignmentService,
		private $userId,
	) {
		parent::__construct($appName, $request);
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
		$response = new DataResponse($card, HTTP::STATUS_OK);
		$response->setETag($card->getEtag());
		return $response;
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
	 *
	 * Get a specific card.
	 */
	public function create($title, $type = 'plain', $order = 999, $description = '', $duedate = null, $labels = [], $users = []) {
		$card = $this->cardService->create($title, $this->request->getParam('stackId'), $type, $order, $this->userId, $description, $duedate);

		foreach ($labels as $labelId) {
			$this->cardService->assignLabel($card->id, $labelId);
		}

		foreach ($users as $user) {
			$this->assignmentService->assignUser($card->id, $user['id'], $user['type']);
		}

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
	public function update($title, $type, $owner, $description = '', $order = 0, $duedate = null, $archived = null, ?bool $done = null) {
		$card = $this->cardService->update($this->request->getParam('cardId'), $title, $this->request->getParam('stackId'), $type, $owner, $description, $order, $duedate, 0, $archived, $done);
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
	 * Assign a user to a card
	 */
	public function assignUser($cardId, $userId, $type = 0) {
		$card = $this->assignmentService->assignUser($cardId, $userId, $type);
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Unassign a user from a card
	 */
	public function unassignUser($cardId, $userId, $type = 0) {
		$card = $this->assignmentService->unassignUser($cardId, $userId, $type);
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
