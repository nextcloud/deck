<?php

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;

/**
 * @group DB
 * @coversDefaultClass \OCA\Deck\Service\BoardService
 */
class TransferOwnershipTest extends \Test\TestCase {
	private const TEST_USER_1 = 'test-share-user1';
	private const TEST_USER_2 = 'test-user2';
	private const TEST_USER_3 = 'test-user3';
	private const TEST_GROUP = 'test-share-user1';

	/** @var BoardService */
	protected $boardService;
	/** @var CardService */
	protected $cardService;
	/** @var StackService */
	protected $stackService;
	/** @var AssignmentMapper */
	protected $assignmentMapper;
	/** @var AssignmentService */
	private $assignmentService;
	/** @var Board */
	private $board;
	private $cards;
	private $stacks;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($backend);
		Server::get(IUserManager::class)->registerBackend($backend);
		$backend->createUser(self::TEST_USER_1, self::TEST_USER_1);
		$backend->createUser(self::TEST_USER_2, self::TEST_USER_2);
		$backend->createUser(self::TEST_USER_3, self::TEST_USER_3);
		// create group
		$groupBackend = new \Test\Util\Group\Dummy();
		$groupBackend->createGroup(self::TEST_GROUP);
		$groupBackend->addToGroup(self::TEST_USER_1, self::TEST_GROUP);
		Server::get(IGroupManager::class)->addBackend($groupBackend);
	}

	public function setUp(): void {
		parent::setUp();
		Server::get(IUserSession::class)->login(self::TEST_USER_1, self::TEST_USER_1);
		$this->boardService = Server::get(BoardService::class);
		$this->stackService = Server::get(StackService::class);
		$this->cardService = Server::get(CardService::class);
		$this->assignmentService = Server::get(AssignmentService::class);
		$this->assignmentMapper = Server::get(AssignmentMapper::class);
		$this->createBoardWithExampleData();
	}

	public function createBoardWithExampleData() {
		$stacks = [];
		$board = $this->boardService->create('Test', self::TEST_USER_1, '000000');
		$id = $board->getId();
		$this->boardService->addAcl($id, Acl::PERMISSION_TYPE_GROUP, self::TEST_GROUP, true, true, true);
		$this->boardService->addAcl($id, Acl::PERMISSION_TYPE_USER, self::TEST_USER_3, false, true, false);
		$stacks[] = $this->stackService->create('Stack A', $id, 1);
		$stacks[] = $this->stackService->create('Stack B', $id, 1);
		$stacks[] = $this->stackService->create('Stack C', $id, 1);
		$cards[] = $this->cardService->create('Card 1', $stacks[0]->getId(), 'text', 0, self::TEST_USER_1);
		$cards[] = $this->cardService->create('Card 2', $stacks[0]->getId(), 'text', 0, self::TEST_USER_1);
		$this->assignmentService->assignUser($cards[0]->getId(), self::TEST_USER_1);
		$this->board = $board;
		$this->cards = $cards;
		$this->stacks = $stacks;
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferBoardOwnership() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2));
		$board = $this->boardService->find($this->board->getId());
		$boardOwner = $board->getOwner();
		$this->assertEquals(self::TEST_USER_2, $boardOwner);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferBoardOwnershipWithData() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2));
		$board = $this->boardService->find($this->board->getId());

		$boardOwner = $board->getOwner();
		$this->assertEquals(self::TEST_USER_2, $boardOwner);

		$cards = $this->cards;
		$newOwnerOwnsTheCards = (bool)array_product(array_filter($cards, function (Card $card) {
			$cardUpdated = $this->cardService->find($card->getId());
			return $cardUpdated->getOwner() === self::TEST_USER_2;
		}));
		$this->assertTrue($newOwnerOwnsTheCards);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferACLOwnership() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2, true));
		$board = $this->boardService->find($this->board->getId());
		$acl = $board->getAcl();
		$this->assertBoardDoesNotHaveAclUser($board, self::TEST_USER_1);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferACLOwnershipPreserveOwner() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2, false));
		$board = $this->boardService->find($this->board->getId());
		$acl = $board->getAcl();
		$this->assertBoardHasAclUser($board, self::TEST_USER_1);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testNoTransferAclOwnershipIfGroupType() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2));
		$board = $this->boardService->find($this->board->getId());
		$acl = $board->getAcl();
		$isGroupInAcl = (bool)array_filter($acl, function ($item) {
			return $item->getParticipant() === self::TEST_GROUP && $item->getType() === Acl::PERMISSION_TYPE_GROUP;
		});
		$this->assertTrue($isGroupInAcl);
	}
	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferCardOwnership() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2, true));
		$card = $this->cardService->find($this->cards[0]->getId());
		$cardOwner = $card->getOwner();
		$this->assertEquals(self::TEST_USER_2, $cardOwner);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferPreserveCardOwnership() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2, false));
		$card = $this->cardService->find($this->cards[0]->getId());
		$cardOwner = $card->getOwner();
		$this->assertEquals(self::TEST_USER_1, $cardOwner);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testReassignCardToNewOwner() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2, true));
		$participantsUIDs = array_map(function ($user) {
			return $user->getParticipant();
		}, $this->assignmentMapper->findAll($this->cards[0]->getId()));
		$this->assertContains(self::TEST_USER_2, $participantsUIDs);
		$this->assertNotContains(self::TEST_USER_1, $participantsUIDs);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testNoReassignCardToNewOwner() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2, false));
		$participantsUIDs = array_map(function ($user) {
			return $user->getParticipant();
		}, $this->assignmentMapper->findAll($this->cards[0]->getId()));
		$this->assertContains(self::TEST_USER_1, $participantsUIDs);
		$this->assertNotContains(self::TEST_USER_2, $participantsUIDs);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testReassignCardToNewParticipantOnlyIfParticipantHasUserType() {
		$this->assignmentService->assignUser($this->cards[1]->getId(), self::TEST_USER_1, Assignment::TYPE_GROUP);
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2));
		$participantsUIDs = array_map(function ($user) {
			return $user->getParticipant();
		}, $this->assignmentMapper->findAll($this->cards[1]->getId()));
		$this->assertContains(self::TEST_USER_1, $participantsUIDs);
		$this->assertNotContains(self::TEST_USER_2, $participantsUIDs);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTargetAlreadyParticipantOfBoard() {
		$this->expectNotToPerformAssertions();
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_3));
	}

	private function assertBoardHasAclUser($board, $userId) {
		$hasUser = (bool)array_filter($board->getAcl(), function ($item) use ($userId) {
			return $item->getParticipant() === $userId && $item->getType() === Acl::PERMISSION_TYPE_USER;
		});
		self::assertTrue($hasUser, 'user ' . $userId . ' should be in the board acl list');
	}

	private function assertBoardDoesNotHaveAclUser($board, $userId) {
		$hasUser = (bool)array_filter($board->getAcl(), function ($item) use ($userId) {
			return $item->getParticipant() === $userId && $item->getType() === Acl::PERMISSION_TYPE_USER;
		});
		self::assertFalse($hasUser, 'user ' . $userId . ' should not be in the board acl list');
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testDontRemoveOldOwnerFromAcl() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2));
		$board = $this->boardService->find($this->board->getId());

		$this->assertBoardDoesNotHaveAclUser($board, self::TEST_USER_2);
		$this->assertBoardHasAclUser($board, self::TEST_USER_3);
		$this->assertBoardHasAclUser($board, self::TEST_USER_1);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testRemoveOldOwnerFromAclForChange() {
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2, true));
		$board = $this->boardService->find($this->board->getId());
		$this->assertBoardDoesNotHaveAclUser($board, self::TEST_USER_2);
		$this->assertBoardHasAclUser($board, self::TEST_USER_3);
		$this->assertBoardDoesNotHaveAclUser($board, self::TEST_USER_1);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testMergePermissions() {
		$this->boardService->addAcl($this->board->getId(), Acl::PERMISSION_TYPE_USER, self::TEST_USER_2, true, false, true);
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_3));
		$board = $this->boardService->find($this->board->getId());
		$acl = $board->getAcl();
		$isMerged = (bool)array_filter($acl, function ($item) {
			return $item->getParticipant() === self::TEST_USER_1
				&& $item->getType() === Acl::PERMISSION_TYPE_USER
				&& $item->getPermission(Acl::PERMISSION_EDIT)
				&& $item->getPermission(Acl::PERMISSION_SHARE)
				&& $item->getPermission(Acl::PERMISSION_MANAGE);
		});
		$this->assertTrue($isMerged);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTargetAlreadyParticipantOfCard() {
		$this->expectNotToPerformAssertions();
		$this->assignmentService->assignUser($this->cards[0]->getId(), self::TEST_USER_3, Assignment::TYPE_USER);
		iterator_to_array($this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_3));
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferSingleBoardAssignment() {
		// Arrange separate board next to the one being transferred
		$board = $this->boardService->create('Test 2', self::TEST_USER_1, '000000');
		$id = $board->getId();
		$this->boardService->addAcl($id, Acl::PERMISSION_TYPE_USER, self::TEST_USER_1, true, true, true);
		$this->boardService->addAcl($id, Acl::PERMISSION_TYPE_GROUP, self::TEST_GROUP, true, true, true);
		$this->boardService->addAcl($id, Acl::PERMISSION_TYPE_USER, self::TEST_USER_3, false, true, false);
		$stacks[] = $this->stackService->create('Stack A', $id, 1);
		$stacks[] = $this->stackService->create('Stack B', $id, 1);
		$stacks[] = $this->stackService->create('Stack C', $id, 1);
		$cards[] = $this->cardService->create('Card 1', $stacks[0]->getId(), 'text', 0, self::TEST_USER_1);
		$cards[] = $this->cardService->create('Card 2', $stacks[0]->getId(), 'text', 0, self::TEST_USER_1);
		$this->assignmentService->assignUser($cards[0]->getId(), self::TEST_USER_1);

		// Act
		$this->boardService->transferBoardOwnership($this->board->getId(), self::TEST_USER_2, true);

		// Assert that the selected board was transferred
		$card = $this->cardService->find($this->cards[0]->getId());
		$this->assertEquals(self::TEST_USER_2, $card->getOwner());

		$participantsUIDs = array_map(function ($assignment) {
			return $assignment->getParticipant();
		}, $this->assignmentMapper->findAll($this->cards[0]->getId()));
		$this->assertContains(self::TEST_USER_2, $participantsUIDs);
		$this->assertNotContains(self::TEST_USER_1, $participantsUIDs);

		// Assert that other board remained unchanged
		$card = $this->cardService->find($cards[0]->getId());
		$this->assertEquals(self::TEST_USER_1, $card->getOwner());

		$participantsUIDs = array_map(function ($assignment) {
			return $assignment->getParticipant();
		}, $this->assignmentMapper->findAll($cards[0]->getId()));
		$this->assertContains(self::TEST_USER_1, $participantsUIDs);
		$this->assertNotContains(self::TEST_USER_2, $participantsUIDs);
	}

	public function tearDown(): void {
		$this->boardService->deleteForce($this->board->getId());
		parent::tearDown();
	}
}
