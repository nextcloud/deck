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

use OCA\Deck\Db\Acl;
use OCA\Deck\Service\BoardService;

use OCP\IRequest;

use OCP\AppFramework\Controller;

use OCP\IUserManager;
use OCP\IGroupManager;

class BoardController extends Controller {
	private $userId;
	private $boardService;
	private $userManager;
	private $groupManager;
	private $userInfo;

	public function __construct($appName,
								IRequest $request,
								IUserManager $userManager,
								IGroupManager $groupManager,
								BoardService $boardService,
								$userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->boardService = $boardService;
		$this->userInfo = $this->getBoardPrerequisites();
	}

	private function getBoardPrerequisites() {
		$groups = $this->groupManager->getUserGroupIds(
			$this->userManager->get($this->userId)
		);
		return [
			'user' => $this->userId,
			'groups' => $groups
		];
	}

	/**
	 * @NoAdminRequired
	 * @RequireNoPermission
	 */
	public function index() {
		return $this->boardService->findAll($this->userInfo);
	}

	/**
	 * @NoAdminRequired
	 * @RequireReadPermission
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function read($boardId) {
		return $this->boardService->find($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @RequireNoPermission
	 * @param $title
	 * @param $color
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function create($title, $color) {
		return $this->boardService->create($title, $this->userId, $color);
	}

	/**
	 * @NoAdminRequired
	 * @RequireManagePermission
	 * @param $id
	 * @param $title
	 * @param $color
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function update($id, $title, $color) {
		return $this->boardService->update($id, $title, $color);
	}

	/**
	 * @NoAdminRequired
	 * @RequireManagePermission
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function delete($boardId) {
		return $this->boardService->delete($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @RequireReadPermission
	 * @param $boardId
	 * @return array|bool
	 * @internal param $userId
	 */
	public function getUserPermissions($boardId) {
		$board = $this->boardService->find($boardId);
		if ($this->userId === $board->getOwner()) {
			return [
				'PERMISSION_READ' => true,
				'PERMISSION_EDIT' => true,
				'PERMISSION_MANAGE' => true,
				'PERMISSION_SHARE' => true,
			];
		}

		return [
			'PERMISSION_READ' => $this->boardService->getPermission($boardId, $this->userId, Acl::PERMISSION_READ),
			'PERMISSION_EDIT' => $this->boardService->getPermission($boardId, $this->userId, Acl::PERMISSION_EDIT),
			'PERMISSION_MANAGE' => $this->boardService->getPermission($boardId, $this->userId, Acl::PERMISSION_MANAGE),
			'PERMISSION_SHARE' => $this->boardService->getPermission($boardId, $this->userId, Acl::PERMISSION_SHARE),
		];

	}

	/**
	 * @NoAdminRequired
	 * @RequireManagePermission
	 * @param $boardId
	 * @param $type
	 * @param $participant
	 * @param $write
	 * @param $invite
	 * @param $manage
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function addAcl($boardId, $type, $participant, $write, $invite, $manage) {
		return $this->boardService->addAcl($boardId, $type, $participant, $write, $invite, $manage);
	}

	/**
	 * @NoAdminRequired
	 * @RequireManagePermission
	 * @param $id
	 * @param $permissionWrite
	 * @param $permissionInvite
	 * @param $permissionManage
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function updateAcl($id, $permissionWrite, $permissionInvite, $permissionManage) {
		return $this->boardService->updateAcl($id, $permissionWrite, $permissionInvite, $permissionManage);
	}

	/**
	 * @NoAdminRequired
	 * @RequireManagePermission
	 * @param $aclId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function deleteAcl($aclId) {
		return $this->boardService->deleteAcl($aclId);
	}

}
