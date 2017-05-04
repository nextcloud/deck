<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Deck\Migration;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Migration\IRepairStep;
use OCP\Migration\IOutput;

class UnknownUsers implements IRepairStep {

	private $userManager;
	private $groupManager;
	private $aclMapper;
	private $boardMapper;

	public function __construct(IUserManager $userManager, IGroupManager $groupManager, AclMapper $aclMapper, BoardMapper $boardMapper) {
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->aclMapper = $aclMapper;
		$this->boardMapper = $boardMapper;
	}

	/*
	 * @inheritdoc
	 */
	public function getName() {
		return 'Delete orphaned ACL rules';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$boards = $this->boardMapper->findAll();
		/** @var Board $board */
		foreach ($boards as $board) {
			$acls = $this->aclMapper->findAll($board->getId());
			/** @var Acl $acl */
			foreach ($acls as $acl) {
				if($acl->getType() === Acl::PERMISSION_TYPE_USER) {
					$user = $this->userManager->get($acl->getParticipant());
					if($user === null) {
						$this->aclMapper->delete($acl);
					}
				}
				if($acl->getType() === Acl::PERMISSION_TYPE_GROUP) {
					$group = $this->groupManager->get($acl->getParticipant());
					if($group === null) {
						$this->aclMapper->delete($acl);
					}
				}

			}
		}
	}
}
