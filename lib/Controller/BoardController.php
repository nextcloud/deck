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

use OCA\Deck\Service\BoardService;

use OCP\IRequest;

use OCP\AppFramework\Controller;

use OCP\IUserManager;
use OCP\IGroupManager;

class BoardController extends Controller {
    private $userId;
    private $boardService;
    protected $userManager;
    protected $groupManager;
	private $userInfo;
    public function __construct($appName,
                                IRequest $request,
                                IUserManager $userManager,
                                IGroupManager $groupManager,
                                BoardService $cardService,
                                $userId) {
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->boardService = $cardService;
        $this->userInfo = $this->getBoardPrerequisites();
    }

    private function getBoardPrerequisites() {
        $groups = $this->groupManager->getUserGroupIds($this->userManager->get($this->userId));
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
        return $this->boardService->find($this->userId, $boardId);
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
        return $this->boardService->update($id, $title, $this->userId, $color);
    }

	/**
	 * @NoAdminRequired
	 * @RequireManagePermission
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
    public function delete($boardId) {
        return $this->boardService->delete($this->userId, $boardId);
    }

	/**
	 * @NoAdminRequired
	 * @RequireReadPermission
	 * @param $boardId
	 * @return
	 */
    public function labels($boardId) {
        return $this->boardService->labels($boardId);
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