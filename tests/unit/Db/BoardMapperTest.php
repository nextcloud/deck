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
class BoardMapperTest extends MapperTestUtility {

	/** @var IDBConnection */
	private $dbConnection;
	/** @var AclMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $aclMapper;
	/** @var BoardMapper */
	private $boardMapper;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;
	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	private $groupManager;

	// Data
	private $acls;
	private $boards;

	public function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->dbConnection = Server::get(IDBConnection::class);
		$this->boardMapper = new BoardMapper(
			$this->dbConnection,
			Server::get(LabelMapper::class),
			Server::get(AclMapper::class),
			Server::get(StackMapper::class),
			$this->userManager,
			$this->groupManager,
			$this->createMock(CirclesService::class),
			$this->createMock(LoggerInterface::class)
		);
		$this->aclMapper = Server::get(AclMapper::class);
		$this->labelMapper = Server::get(LabelMapper::class);

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
		foreach ($this->boards as $board) {
			$board->resetUpdatedFields();
		}
	}
	public function getAcl($type = 'user', $participant = 'admin', $edit = false, $share = false, $manage = false, $boardId = 123): ACL {
		$acl = new Acl();
		$acl->setParticipant($participant);
		$acl->setType('user');
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
		$acl->setPermissionManage($manage);
		$acl->setBoardId($boardId);
		return $acl;
	}

	public function getBoard($title, $owner): Board {
		$board = new Board();
		$board->setTitle($title);
		$board->setOwner($owner);
		return $board;
	}

	public function testFind() {
		$actual = $this->boardMapper->find($this->boards[0]->getId());
		/** @var Board $expected */
		$expected = clone $this->boards[0];
		$expected->setShared(-1);
		$expected->resetUpdatedFields();
		$this->assertEquals($expected, $actual);
	}

	public function testFindAllByUser() {
		$actual = $this->boardMapper->findAllByUser('user1');
		$expected = [
			$this->boards[0],
			$this->boards[1],
			$this->boards[2]
		];
		foreach ($expected as $e) {
			foreach ($actual as $a) {
				if ($e->getId() === $a->getId()) {
					$this->assertEquals($e->getTitle(), $a->getTitle());
				}
			}
		}
	}

	public function testFindAll() {
		$actual = $this->boardMapper->findAll();
		$this->assertEquals(1, count(array_filter($actual, function ($card) {
			return $card->getId() === $this->boards[0]->getId();
		})));
		$this->assertEquals(1, count(array_filter($actual, function ($card) {
			return $card->getId() === $this->boards[1]->getId();
		})));
		$this->assertEquals(1, count(array_filter($actual, function ($card) {
			return $card->getId() === $this->boards[2]->getId();
		})));
	}

	public function testFindAllToDelete() {
		$this->boards[0]->setDeletedAt(1);
		$this->boards[0] = $this->boardMapper->update($this->boards[0]);

		$actual = $this->boardMapper->findToDelete();
		$this->boards[0]->resetUpdatedFields();
		$this->assertEquals([$this->boards[0]], $actual);

		$this->boards[0]->setDeletedAt(0);
		$this->boardMapper->update($this->boards[0]);
	}

	public function testFindWithLabels() {
		$actual = $this->boardMapper->find($this->boards[0]->getId(), true, false);
		/** @var Board $expected */
		$expected = $this->boards[0];
		$expected->setLabels([]);
		$this->assertEquals($expected->getLabels(), $actual->getLabels());
	}

	public function testFindWithAcl() {
		$actual = $this->boardMapper->find($this->boards[0]->getId(), false, true);
		$expected = [$this->acls[1], $this->acls[2]];
		$this->assertEquals($expected, $actual->getAcl());
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
