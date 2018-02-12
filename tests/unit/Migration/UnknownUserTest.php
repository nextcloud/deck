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
use OCP\Migration\IOutput;

class UnknownUserTest extends \Test\TestCase {

	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var AclMapper */
	private $aclMapper;
	/** @var BoardMapper */
	private $boardMapper;
	/** @var UnknownUsers */
	private $unknownUsers;

	public function setUp() {
		parent::setUp();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->unknownUsers = new UnknownUsers($this->userManager, $this->groupManager, $this->aclMapper, $this->boardMapper);
	}



	public function testGetName() {
		$this->assertEquals('Delete orphaned ACL rules', $this->unknownUsers->getName());
	}

	public function testRun() {
		/** @var IOutput $output */
		$output = $this->createMock(IOutput::class);
		$boards = [
			$this->getBoard(1,'Test', 'admin'),
		];
		$acl = [
			$this->getAcl(Acl::PERMISSION_TYPE_USER, 'existing', 1),
			$this->getAcl(Acl::PERMISSION_TYPE_USER, 'not existing', 1),
			$this->getAcl(Acl::PERMISSION_TYPE_GROUP, 'existing', 1),
			$this->getAcl(Acl::PERMISSION_TYPE_GROUP, 'not existing', 1),
		];
		$this->aclMapper->expects($this->at(0))
			->method('findAll')
			->with(1)
			->willReturn($acl);

		$this->userManager->expects($this->at(0))
			->method('get')
			->with('existing')
			->willReturn(true);
		$this->userManager->expects($this->at(1))
			->method('get')
			->with('not existing')
			->willReturn(null);
		$this->groupManager->expects($this->at(0))
			->method('get')
			->with('existing')
			->willReturn(true);
		$this->groupManager->expects($this->at(1))
			->method('get')
			->with('not existing')
			->willReturn(null);

		$this->boardMapper->expects($this->once())
			->method('findAll')
			->willReturn($boards);

		$this->aclMapper->expects($this->at(1))
			->method('delete')
			->with($acl[1]);
		$this->aclMapper->expects($this->at(2))
			->method('delete')
			->with($acl[3]);

		$this->unknownUsers->run($output);
	}


	/** @return Acl */
	public function getAcl($type=Acl::PERMISSION_TYPE_USER, $participant='admin', $boardId=123) {
		$acl = new Acl();
		$acl->setParticipant($participant);
		$acl->setType($type);
		$acl->setBoardId($boardId);
		return $acl;
	}

	/** @return Board */
	public function getBoard($id, $title, $owner) {
		$board = new Board();
		$board->setId($id);
		$board->setTitle($title);
		$board->setOwner($owner);
		return $board;
	}
}