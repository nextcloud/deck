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

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Label;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IL10N;


use \OCA\Deck\Db\Board;
use \OCA\Deck\Db\BoardMapper;
use \OCA\Deck\Db\LabelMapper;
use OCP\IUserManager;


class BoardService {

    private $boardMapper;
    private $aclMapper;
    private $labelMapper;
    private $logger;
    private $l10n;

    public function __construct(BoardMapper $boardMapper,
                                ILogger $logger,
                                IL10N $l10n,
                                LabelMapper $labelMapper,
                                AclMapper $aclMapper,
								IUserManager $userManager,
								IGroupManager $groupManager) {
        $this->boardMapper = $boardMapper;
        $this->labelMapper = $labelMapper;
        $this->aclMapper = $aclMapper;
        $this->logger = $logger;
        $this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
    }

    public function findAll($userInfo) {
        $userBoards = $this->boardMapper->findAllByUser($userInfo['user']);
        $groupBoards = $this->boardMapper->findAllByGroups($userInfo['user'], $userInfo['groups']);
        return array_merge($userBoards, $groupBoards);
    }

    public function find($boardId) {
        $board = $this->boardMapper->find($boardId);
        return $board;
    }

    public function create($title, $userId, $color) {
        $board = new Board();
        $board->setTitle($title);
        $board->setOwner($userId);
        $board->setColor($color);
        $new_board = $this->boardMapper->insert($board);

        // create new labels
        $default_labels = [
            '31CC7C' => $this->l10n->t('Finished'),
            '317CCC' => $this->l10n->t('To review'),
            'FF7A66' => $this->l10n->t('Action needed'),
            'F1DB50' => $this->l10n->t('Maybe')];
        $labels = [];
        foreach ($default_labels as $color=>$title) {
            $label = new Label();
            $label->setColor($color);
            $label->setTitle($title);
            $label->setBoardId($new_board->getId());
            $labels[] = $this->labelMapper->insert($label);
        }
        $new_board->setLabels($labels);
        return $new_board;

    }

    public function delete($id) {
        return $this->boardMapper->delete($this->find($id));
    }

    public function update($id, $title, $color) {
        $board = $this->find($id);
        $board->setTitle($title);
        $board->setColor($color);
        return $this->boardMapper->update($board);
    }
    

    public function addAcl($boardId, $type, $participant, $write, $invite, $manage) {
        $acl = new Acl();
        $acl->setBoardId($boardId);
        $acl->setType($type);
        $acl->setParticipant($participant);
        $acl->setPermissionWrite($write);
        $acl->setPermissionInvite($invite);
        $acl->setPermissionManage($manage);
        return $this->aclMapper->insert($acl);
    }

    public function updateAcl($id, $write, $invite, $manage) {
        $acl = $this->aclMapper->find($id);
        $acl->setPermissionWrite($write);
        $acl->setPermissionInvite($invite);
        $acl->setPermissionManage($manage);
        return $this->aclMapper->update($acl);
    }

    public function deleteAcl($id) {
        $acl = $this->aclMapper->find($id);
        return $this->aclMapper->delete($acl);
    }

	/**
	 * @param $boardId
	 * @param $user
	 * @param $permission
	 * @return bool
	 */
	public function getPermission($boardId, $user, $permission) {
		$acls = $this->aclMapper->findAll($boardId);
		// check for users
		foreach ($acls as $acl) {
			if ($acl->getType() === "user" && $acl->getParticipant() === $user) {
				return $acl->getPermission($permission);
			}
		}
		// check for groups
		$hasGroupPermission = false;
		foreach ($acls as $acl) {
			if (!$hasGroupPermission && $acl->getType() === "group" && $this->groupManager->isInGroup($user, $acl->getParticipant())) {
				$hasGroupPermission = $acl->getPermission($permission);
			}
		}
		return $hasGroupPermission;
	}
}