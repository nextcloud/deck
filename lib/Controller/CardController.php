<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
use OCP\IRequest;
use OCP\AppFramework\Controller;

class CardController extends Controller {
	private $userId;
	private $cardService;
	private $assignmentService;

	public function __construct($appName, IRequest $request, CardService $cardService, AssignmentService $assignmentService, $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->cardService = $cardService;
		$this->assignmentService = $assignmentService;
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function read($cardId) {
		return $this->cardService->find($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $stackId
	 * @param $order
	 * @return array
	 */
	public function reorder($cardId, $stackId, $order) {
		return $this->cardService->reorder((int)$cardId, (int)$stackId, (int)$order);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $title
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function rename($cardId, $title) {
		return $this->cardService->rename($cardId, $title);
	}

	/**
	 * @NoAdminRequired
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param int $order
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function create($title, $stackId, $type = 'plain', $order = 999, string $description = '') {
		return $this->cardService->create($title, $stackId, $type, $order, $this->userId, $description);
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param $order
	 * @param $description
	 * @param $duedate
	 * @param $deletedAt
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function update($id, $title, $stackId, $type, $order, $description, $duedate, $deletedAt) {
		return $this->cardService->update($id, $title, $stackId, $type, $this->userId, $description, $order, $duedate, $deletedAt);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function delete($cardId) {
		return $this->cardService->delete($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function deleted($boardId) {
		return $this->cardService->fetchDeleted($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function archive($cardId) {
		return $this->cardService->archive($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function unarchive($cardId) {
		return $this->cardService->unarchive($cardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $labelId
	 */
	public function assignLabel($cardId, $labelId) {
		$this->cardService->assignLabel($cardId, $labelId);
	}

	/**
	 * @NoAdminRequired
	 * @param $cardId
	 * @param $labelId
	 */
	public function removeLabel($cardId, $labelId) {
		$this->cardService->removeLabel($cardId, $labelId);
	}

	/**
	 * @NoAdminRequired
	 */
	public function assignUser($cardId, $userId, $type = 0) {
		return $this->assignmentService->assignUser($cardId, $userId, $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function unassignUser($cardId, $userId, $type = 0) {
		return $this->assignmentService->unassignUser($cardId, $userId, $type);
	}
}
