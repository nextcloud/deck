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

use OCA\Deck\NotFoundException;
use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\StackService;
use OCA\Deck\Service\CardService;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;

/**
 * @group DB
 * @coversDefaultClass \OCA\Deck\Db\AssignmentMapper
 */
class AssignmentMapperTest extends \Test\TestCase {
	private const TEST_USER1 = 'test-share-user1';
	private const TEST_USER3 = 'test-share-user3';
	private const TEST_USER2 = 'test-share-user2';
	private const TEST_USER4 = 'test-share-user4';
	private const TEST_GROUP1 = 'test-share-group1';

	/** @var BoardService */
	protected $boardService;
	/** @var CardService */
	protected $cardService;
	/** @var StackService */
	protected $stackService;
	/** @var AssignmentMapper */
	protected $assignedUsersMapper;
	/** @var AssignmentService */
	private $assignmentService;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($backend);
		Server::get(IUserManager::class)->registerBackend($backend);
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
		Server::get(IGroupManager::class)->addBackend($groupBackend);
	}

	public function setUp(): void {
		parent::setUp();
		Server::get(IUserSession::class)->login(self::TEST_USER1, self::TEST_USER1);
		$this->boardService = Server::get(BoardService::class);
		$this->stackService = Server::get(StackService::class);
		$this->cardService = Server::get(CardService::class);
		$this->assignmentService = Server::get(AssignmentService::class);
		$this->assignedUsersMapper = Server::get(AssignmentMapper::class);
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

	/**
	 * @covers ::findAll
	 */
	public function testFind() {
		$uids = [];
		$this->assignmentService->assignUser($this->cards[0]->getId(), self::TEST_USER1);
		$this->assignmentService->assignUser($this->cards[0]->getId(), self::TEST_USER2);

		$assignedUsers = $this->assignedUsersMapper->findAll($this->cards[0]->getId());
		foreach ($assignedUsers as $user) {
			$uids[$user->getParticipant()] = $user;
		}
		$this->assertArrayHasKey(self::TEST_USER1, $uids);
		$this->assertArrayHasKey(self::TEST_USER2, $uids);
		$this->assertArrayNotHasKey(self::TEST_USER3, $uids);
		$this->assertArrayNotHasKey(self::TEST_USER4, $uids);

		$this->assignmentService->unassignUser($this->cards[0]->getId(), self::TEST_USER1);
		$this->assignmentService->unassignUser($this->cards[0]->getId(), self::TEST_USER2);
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
		$assignment = new Assignment();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant(self::TEST_USER4);
		$assignment->setType(Assignment::TYPE_USER);
		$this->assignedUsersMapper->insert($assignment);

		$actual = $this->assignedUsersMapper->findAll($this->cards[1]->getId());
		$this->assertEquals(1, count($actual));
		$this->assertEquals($this->cards[1]->getId(), $actual[0]->getCardId());
		$this->assertEquals(self::TEST_USER4, $actual[0]->getParticipant());
	}

	/**
	 * @covers ::insert
	 */
	public function testInsertInvalidUser() {
		$assignment = new Assignment();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant('invalid-username');
		$assignment->setType(Assignment::TYPE_USER);
		$this->expectException(NotFoundException::class);
		$this->assignedUsersMapper->insert($assignment);
	}

	/**
	 * @covers ::mapParticipant
	 */
	public function testMapParticipant() {
		$assignment = new Assignment();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant(self::TEST_USER4);
		$assignment->setType(Assignment::TYPE_USER);
		$this->assignedUsersMapper->mapParticipant($assignment);
		$this->assertInstanceOf(User::class, $assignment->resolveParticipant());

		$assignment = new Assignment();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant('invalid-username');
		$assignment->setType(Assignment::TYPE_USER);
		$this->assignedUsersMapper->mapParticipant($assignment);
		$this->assertEquals('invalid-username', $assignment->resolveParticipant());
	}

	public function testIsUserAssigned() {
		$assignment = new Assignment();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant(self::TEST_USER4);
		$assignment->setType(Assignment::TYPE_USER);
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER4));

		$assignment = $this->assignedUsersMapper->insert($assignment);
		$actual = $this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER4);
		$this->assignedUsersMapper->delete($assignment);
		$this->assertTrue($actual);

		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER4));
	}

	public function testIsUserAssignedGroup() {
		$assignment = new Assignment();
		$assignment->setCardId($this->cards[1]->getId());
		$assignment->setParticipant('group');
		$assignment->setType(Assignment::TYPE_GROUP);
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER1));
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER2));
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER3));
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER4));

		$assignment = $this->assignedUsersMapper->insert($assignment);
		$this->assertTrue($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER1));
		$this->assertTrue($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER2));
		$this->assertTrue($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER3));
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER4));
		$this->assignedUsersMapper->delete($assignment);

		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER1));
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER2));
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER3));
		$this->assertFalse($this->assignedUsersMapper->isUserAssigned($this->cards[1]->getId(), self::TEST_USER4));
	}

	public function tearDown(): void {
		$this->boardService->deleteForce($this->board->getId());
		parent::tearDown();
	}
}
