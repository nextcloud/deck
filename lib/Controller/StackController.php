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

use OCA\Deck\Service\StackService;

use OCP\IRequest;

use OCP\AppFramework\Controller;

class StackController extends Controller {
	private $userId;
	private $stackService;
	public function __construct($appName, IRequest $request, StackService $stackService, $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->stackService = $stackService;
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return array
	 */
	public function index($boardId) {
		return $this->stackService->findAll($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return array
	 */
	public function archived($boardId) {
		return $this->stackService->findAllArchived($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $title
	 * @param $boardId
	 * @param int $order
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function create($title, $boardId, $order = 999) {
		return $this->stackService->create($title, $boardId, $order);
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $title
	 * @param $boardId
	 * @param $order
	 * @param $deletedAt
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function update($id, $title, $boardId, $order, $deletedAt) {
		return $this->stackService->update($id, $title, $boardId, $order, $deletedAt);
	}

	/**
	 * @NoAdminRequired
	 * @param $stackId
	 * @param $order
	 * @return array
	 */
	public function reorder($stackId, $order) {
		return $this->stackService->reorder((int)$stackId, (int)$order);
	}

	/**
	 * @NoAdminRequired
	 * @param $stackId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function delete($stackId) {
		return $this->stackService->delete($stackId);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function deleted($boardId) {
		return $this->stackService->fetchDeleted($boardId);
	}
}
