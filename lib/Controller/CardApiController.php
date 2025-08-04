<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Model\OptionalNullableValue;
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
	public function update($title, $type, $owner, $description = '', $order = 0, $duedate = null, $archived = null) {
		$done = array_key_exists('done', $this->request->getParams()) ? new OptionalNullableValue($this->request->getParam('done', null)) : null;
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
	 * Archive card
	 */
	public function archive($cardId) {
		$card = $this->cardService->archive($cardId);
		return new DataResponse($card, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Unarchive card
	 */
	public function unarchive($cardId) {
		$card = $this->cardService->unarchive($cardId);
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
		$card = $this->cardService->reorder((int)$this->request->getParam('cardId'), (int)$stackId, (int)$order);
		return new DataResponse($card, HTTP::STATUS_OK);
	}
}
