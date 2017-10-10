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

namespace OCA\Deck\Db;

use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\StackService;
use OCA\Deck\Service\CardService;
use OCP\IDBConnection;

/**
 * @group DB
 * @coversDefaultClass OCA\Deck\Db\AssignedUsersMapper
 */
class AssignedUsersMapperTest extends \Test\TestCase {
	const TEST_USER1 = "test-share-user1";
	const TEST_USER2 = "test-share-user2";
	const TEST_USER3 = "test-share-user3";
	const TEST_USER4 = "test-share-user4";
	const TEST_GROUP1 = "test-share-group1";

	/** @var BoardService */
	protected $boardService;
	/** @var CardService */
	protected $cardService;
	/** @var StackService */
	protected $stackService;
	/** @var \OCA\Deck\Db\AssignedUsersMapper */
	protected $assignedUsersMapper;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		$backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($backend);
		\OC::$server->getUserManager()->registerBackend($backend);
		$backend->createUser(self::TEST_USER1, self::TEST_USER1);
		$backend->createUser(self::TEST_USER2, self::TEST_USER2);
		$backend->createUser(self::TEST_USER3, self::TEST_USER3);
		$backend->createUser(self::TEST_USER4, self::TEST_USER4);
		// create group
		$groupBackend = new \Test\Util\Group\Dummy();
		$groupBackend->createGroup(self::TEST_GROUP1);
		$groupBackend->createGroup('group');
		$groupBackend->createGroup('group1');
		$groupBackend->createGroup('group2');
		$groupBackend->createGroup('group3');
		$groupBackend->addToGroup(self::TEST_USER1, 'group');
		$groupBackend->addToGroup(self::TEST_USER2, 'group');
		$groupBackend->addToGroup(self::TEST_USER3, 'group');
		$groupBackend->addToGroup(self::TEST_USER2, 'group1');
		$groupBackend->addToGroup(self::TEST_USER3, 'group2');
		$groupBackend->addToGroup(self::TEST_USER4, 'group3');
		$groupBackend->addToGroup(self::TEST_USER2, self::TEST_GROUP1);
		\OC::$server->getGroupManager()->addBackend($groupBackend);
	}

	public function setUp() {
		parent::setUp();
		\OC::$server->getUserSession()->login(self::TEST_USER1, self::TEST_USER1);
		$this->boardService = \OC::$server->query("\OCA\Deck\Service\BoardService");
		$this->stackService = \OC::$server->query("\OCA\Deck\Service\StackService");
		$this->cardService = \OC::$server->query("\OCA\Deck\Service\CardService");
		$this->assignedUsersMapper = \OC::$server->query(\OCA\Deck\Db\AssignedUsersMapper::class);
		$this->createBoardWithExampleData();
	}

	public function createBoardWithExampleData() {
		$stacks = [];
		$board = $this->boardService->create('Test', self::TEST_USER1, '000000');
		$id = $board->getId();
		$this->boardService->addAcl($board->getId(), Acl::PERMISSION_TYPE_USER, self::TEST_USER1, true, true, true);
		$this->boardService->addAcl($board->getId(), Acl::PERMISSION_TYPE_USER, self::TEST_USER2, true, true, true);
		$this->boardService->addAcl($board->getId(), Acl::PERMISSION_TYPE_GROUP, 'group3', true, true, true);
		$stacks[] = $this->stackService->create('Stack A', $id, 1);
		$stacks[] = $this->stackService->create('Stack B', $id, 1);
		$stacks[] = $this->stackService->create('Stack C', $id, 1);
		$cards[] = $this->cardService->create('Card 1', $stacks[0]->getId(), 'text', 0, self::TEST_USER1);
		$cards[] = $this->cardService->create('Card 2', $stacks[0]->getId(), 'text', 0, self::TEST_USER1);
		$this->board = $board;
		$this->cards = $cards;
		$this->stacks = $stacks;
	}

	public function testConstructor() {
		$this->assertAttributeInstanceOf(IDBConnection::class, 'db', $this->assignedUsersMapper);
		$this->assertAttributeEquals(AssignedUsers::class, 'entityClass', $this->assignedUsersMapper);
		$this->assertAttributeEquals('*PREFIX*deck_assigned_users', 'tableName', $this->assignedUsersMapper);
	}

	/**
	 * @covers ::find
	 */
	public function testFind() {
		$uids = [];
		$this->cardService->assignUser($this->cards[0]->getId(), self::TEST_USER1);
		$this->cardService->assignUser($this->cards[0]->getId(), self::TEST_USER4);

		$assignedUsers = $this->assignedUsersMapper->find($this->cards[0]->getId());
		foreach ($assignedUsers as $user) {
			$uids[$user->getParticipant()] = $user;
		}
		$this->assertArrayHasKey(self::TEST_USER1, $uids);
		$this->assertArrayNotHasKey(self::TEST_USER2, $uids);
		$this->assertArrayNotHasKey(self::TEST_USER3, $uids);
		$this->assertArrayHasKey(self::TEST_USER4, $uids);

		$this->cardService->unassignUser($this->cards[0]->getId(), self::TEST_USER1);
		$this->cardService->unassignUser($this->cards[0]->getId(), self::TEST_USER4);
	}

	/**
	 * @covers ::isOwner
	 */
	public function testIsOwner() {
		$this->assertTrue($this->assignedUsersMapper->isOwner(self::TEST_USER1, $this->cards[0]->getId()));
		$this->assertFalse($this->assignedUsersMapper->isOwner(self::TEST_USER2, $this->cards[0]->getId()));
	}

	/**
	 * @covers ::findBoardId
	 */
	public function testFindBoardId() {
		$this->assertEquals($this->board->getId(), $this->assignedUsersMapper->findBoardId($this->cards[0]->getId()));
	}

	/**
	 * @covers ::insert
	 */
	public function testInsert() {
		$assignment = new AssignedUsers();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant(self::TEST_USER4);
		$this->assignedUsersMapper->insert($assignment);

		$actual = $this->assignedUsersMapper->find($this->cards[1]->getId());
		$this->assertEquals(1, count($actual));
		$this->assertEquals($this->cards[1]->getId(), $actual[0]->getCardId());
		$this->assertEquals(self::TEST_USER4, $actual[0]->getParticipant());
	}

	/**
	 * @covers ::insert
	 */
	public function testInsertInvalidUser() {
		$assignment = new AssignedUsers();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant('invalid-username');
		$actual = $this->assignedUsersMapper->insert($assignment);
		$this->assertNull($actual);
	}

	/**
	 * @covers ::mapParticipant
	 */
	public function testMapParticipant() {
		$assignment = new AssignedUsers();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant(self::TEST_USER4);
		$this->assignedUsersMapper->mapParticipant($assignment);
		$this->assertInstanceOf(User::class, $assignment->resolveParticipant());

		$assignment = new AssignedUsers();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant('invalid-username');
		$this->assignedUsersMapper->mapParticipant($assignment);
		$this->assertEquals('invalid-username', $assignment->resolveParticipant());
	}

	public function tearDown() {
		$this->boardService->deleteForce($this->board->getId());
		parent::tearDown();
	}
}