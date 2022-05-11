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

namespace OCA\Deck\Db;

use OCA\Deck\Service\CirclesService;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use Test\AppFramework\Db\MapperTestUtility;

/**
 * @group DB
 */
class AclMapperTest extends MapperTestUtility {
	private $dbConnection;
	private $aclMapper;
	private $boardMapper;
	private $userManager;
	private $groupManager;

	// Data
	private $acls;
	private $boards;

	public function setup(): void {
		parent::setUp();

		$this->dbConnection = Server::get(IDBConnection::class);
		$this->aclMapper = new AclMapper($this->dbConnection);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->boardMapper = new BoardMapper(
			$this->dbConnection,
			Server::get(LabelMapper::class),
			$this->aclMapper,
			Server::get(StackMapper::class),
			$this->userManager,
			$this->groupManager,
			$this->createMock(CirclesService::class),
			$this->createMock(LoggerInterface::class)
		);

		$this->boards = [
			$this->boardMapper->insert($this->getBoard('MyBoard 1', 'user1')),
			$this->boardMapper->insert($this->getBoard('MyBoard 2', 'user2')),
			$this->boardMapper->insert($this->getBoard('MyBoard 3', 'user3'))
		];
		$this->acls = [
			$this->aclMapper->insert($this->getAcl('user', 'user1', false, false, false, $this->boards[1]->getId())),
			$this->aclMapper->insert($this->getAcl('user', 'user2', true, false, false, $this->boards[0]->getId())),
			$this->aclMapper->insert($this->getAcl('user', 'user3', true, true, false, $this->boards[0]->getId())),
			$this->aclMapper->insert($this->getAcl('user', 'user1', false, false, false, $this->boards[2]->getId()))
		];

		foreach ($this->acls as $acl) {
			$acl->resetUpdatedFields();
		}
	}
	/** @return Acl */
	public function getAcl($type = 'user', $participant = 'admin', $edit = false, $share = false, $manage = false, $boardId = 123) {
		$acl = new Acl();
		$acl->setParticipant($participant);
		$acl->setType('user');
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
		$acl->setPermissionManage($manage);
		$acl->setBoardId($boardId);
		return $acl;
	}

	/** @return Board */
	public function getBoard($title, $owner) {
		$board = new Board();
		$board->setTitle($title);
		$board->setOwner($owner);
		return $board;
	}

	public function testFindAllDatabase() {
		$actual = $this->aclMapper->findAll($this->boards[0]->getId());
		$expected = [$this->acls[1], $this->acls[2]];
		$this->assertEquals($expected, $actual);
	}
	public function testIsOwnerDatabase() {
		$this->assertTrue($this->aclMapper->isOwner('user2', $this->acls[0]->getId()));
		$this->assertTrue($this->aclMapper->isOwner('user1', $this->acls[1]->getId()));
		$this->assertTrue($this->aclMapper->isOwner('user1', $this->acls[2]->getId()));
		$this->assertTrue($this->aclMapper->isOwner('user3', $this->acls[3]->getId()));
		$this->assertFalse($this->aclMapper->isOwner('user3', $this->acls[0]->getId()));
		$this->assertFalse($this->aclMapper->isOwner('user1', $this->acls[0]->getId()));
	}

	public function testFindBoardIdDatabase() {
		$this->assertEquals($this->boards[0]->getId(), $this->aclMapper->findBoardId($this->acls[1]->getId()));
	}


	public function tearDown(): void {
		parent::tearDown();
		foreach ($this->acls as $acl) {
			$this->aclMapper->delete($acl);
		}
		foreach ($this->boards as $board) {
			$this->boardMapper->delete($board);
		}
	}
}
