<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Activity;

use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\PermissionService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ActivityManagerTest extends TestCase {

	/** @var ActivityManager */
	private $activityManager;
	/** @var IManager|MockObject */
	private $manager;
	/** @var PermissionService|MockObject */
	private $permissionService;
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var AclMapper|MockObject */
	private $aclMapper;
	/** @var IFactory|MockObject */
	private $l10nFactory;
	/** @var IL10N|MockObject */
	private $l10n;
	/** @var string */
	private $userId = 'admin';

	public function setUp(): void {
		$this->manager = $this->createMock(IManager::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->activityManager = new ActivityManager(
			$this->manager,
			$this->permissionService,
			$this->boardMapper,
			$this->cardMapper,
			$this->stackMapper,
			$this->aclMapper,
			$this->l10nFactory,
			$this->userId
		);
	}

	public function testGetActivityFormatOwn() {
		$managerClass = new \ReflectionClass(ActivityManager::class);
		$this->l10n->expects(self::any())
			->method('t')
			->will($this->returnCallback(function ($s) {
				return $s;
			}));
		$this->l10nFactory->method('get')
			->with('deck', 'cz')
			->willReturn($this->l10n);

		foreach ($managerClass->getConstants() as $constant => $value) {
			if (strpos($constant, 'SUBJECT') === 0) {
				$format = $this->activityManager->getActivityFormat('cz', $value, [], false);
				if ($format !== '') {
					$this->assertStringContainsString('{user}', $format);
				} else {
					self::addWarning('No activity string found for '. $constant);
				}
				$format = $this->activityManager->getActivityFormat('cz', $value, [], true);
				if ($format !== '') {
					$this->assertStringStartsWith('You', $format);
				} else {
					self::addWarning('No own activity string found for '. $constant);
				}
			}
		}
	}

	private function expectEventCreation($subject, $subjectParams) {
		$event = $this->createMock(IEvent::class);
		$this->manager->expects(self::once())
			->method('generateEvent')
			->willReturn($event);
		$event->expects(self::once())->method('setApp')->willReturn($event);
		$event->expects(self::once())->method('setType')->willReturn($event);
		$event->expects(self::once())->method('setAuthor')->willReturn($event);
		$event->expects(self::once())->method('setObject')->willReturn($event);
		$event->expects(self::once())->method('setSubject')->with($subject, $subjectParams)->willReturn($event);
		$event->expects(self::once())->method('setTimestamp')->willReturn($event);
		return $event;
	}

	public function testCreateEvent() {
		$board = new Board();
		$board->setTitle('');
		$this->boardMapper->expects(self::once())
			->method('find')
			->willReturn($board);
		$event = $this->expectEventCreation(ActivityManager::SUBJECT_BOARD_CREATE, [
			'author' => 'admin'
		]);
		$actual = $this->invokePrivate($this->activityManager, 'createEvent', [
			ActivityManager::DECK_OBJECT_BOARD,
			$board,
			ActivityManager::SUBJECT_BOARD_CREATE
		]);
		$this->assertEquals($event, $actual);
	}

	public function testCreateEventDescription() {
		$board = new Board();
		$board->setTitle('');
		$this->boardMapper->expects(self::once())
			->method('find')
			->willReturn($board);

		$card = Card::fromRow([
			'id' => 123,
			'title' => 'My card',
			'description' => str_repeat('A', 1000),
		]);
		$this->cardMapper->expects(self::any())
			->method('find')
			->willReturn($card);

		$stack = Stack::fromRow([]);
		$this->stackMapper->expects(self::any())
			->method('find')
			->willReturn($stack);

		$expectedCard = $card->jsonSerialize();
		unset($expectedCard['description']);
		$event = $this->expectEventCreation(ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION, [
			'card' => $expectedCard,
			'stack' => $stack->jsonSerialize(),
			'board' => $board->jsonSerialize(),
			'diff' => true,
			'author' => 'admin',
			'after' => str_repeat('C', 2000),
		]);

		$actual = $this->invokePrivate($this->activityManager, 'createEvent', [
			ActivityManager::DECK_OBJECT_CARD,
			$card,
			ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION,
			[
				'before' => str_repeat('B', 2000),
				'after' => str_repeat('C', 2000)
			],
		]);
		$this->assertEquals($event, $actual);
	}

	public function testCreateEventLongDescription() {
		$board = new Board();
		$board->setTitle('');
		$this->boardMapper->expects(self::once())
			->method('find')
			->willReturn($board);

		$card = new Card();
		$card->setDescription(str_repeat('A', 5000));
		$card->setTitle('My card');
		$card->setId(123);
		$this->cardMapper->expects(self::any())
			->method('find')
			->willReturn($card);

		$stack = new Stack();
		$this->stackMapper->expects(self::any())
			->method('find')
			->willReturn($stack);

		$expectedCard = $card->jsonSerialize();
		unset($expectedCard['description']);
		$event = $this->expectEventCreation(ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION, [
			'card' => $expectedCard,
			'stack' => $stack->jsonSerialize(),
			'board' => $board->jsonSerialize(),
			'diff' => true,
			'author' => 'admin',
			'after' => str_repeat('C', 2000) . '...',
		]);

		$actual = $this->invokePrivate($this->activityManager, 'createEvent', [
			ActivityManager::DECK_OBJECT_CARD,
			$card,
			ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION,
			[
				'before' => str_repeat('B', 5000),
				'after' => str_repeat('C', 5000)
			],
		]);
		$this->assertEquals($event, $actual);
	}

	public function testCreateEventLabel() {
		$board = Board::fromRow([
			'title' => 'My board'
		]);
		$this->boardMapper->expects(self::once())
			->method('find')
			->willReturn($board);

		$card = Card::fromParams([]);
		$card->setDescription(str_repeat('A', 5000));
		$card->setTitle('My card');
		$card->setId(123);
		$this->cardMapper->expects(self::any())
			->method('find')
			->willReturn($card);

		$stack = Stack::fromParams([]);
		$this->stackMapper->expects(self::any())
			->method('find')
			->willReturn($stack);

		$event = $this->expectEventCreation(ActivityManager::SUBJECT_CARD_UPDATE_TITLE, [
			'card' => [
				'id' => 123,
				'title' => 'My card',
				'archived' => false,
			],
			'stack' => $stack,
			'board' => $board,
			'author' => 'admin',
		]);

		$actual = $this->invokePrivate($this->activityManager, 'createEvent', [
			ActivityManager::DECK_OBJECT_CARD,
			$card,
			ActivityManager::SUBJECT_CARD_UPDATE_TITLE
		]);
		$this->assertEquals($event, $actual);
	}

	public function dataSendToUsers() {
		return [
			[ActivityManager::DECK_OBJECT_BOARD],
			[ActivityManager::DECK_OBJECT_CARD],
		];
	}

	private function mockUser($uid) {
		$user = $this->createMock(IUser::class);
		$user->expects(self::any())
			->method('getUID')
			->willReturn($uid);
		return $user;
	}
	/**
	 * @dataProvider dataSendToUsers
	 */
	public function testSendToUser($objectType) {
		$users = [
			$this->mockUser('user1'),
			$this->mockUser('user2'),
		];
		$event = $this->createMock(IEvent::class);

		$event->expects(self::once())
			->method('getObjectType')
			->willReturn($objectType);
		$event->expects(self::once())
			->method('getObjectId')
			->willReturn(1);
		$event->expects(self::exactly(2))
			->method('setAffectedUser')
			->withConsecutive(
				['user1'],
				['user2'],
			)
			->willReturnSelf();

		$mapper = null;
		switch ($objectType) {
			case ActivityManager::DECK_OBJECT_BOARD:
				$mapper = $this->boardMapper;
				break;
			case ActivityManager::DECK_OBJECT_CARD:
				$mapper = $this->cardMapper;
				break;
		}
		$mapper->expects(self::once())
			->method('findBoardId')
			->willReturn(123);
		$this->permissionService->expects(self::once())
			->method('findUsers')
			->willReturn($users);

		$this->manager->expects(self::exactly(2))
			->method('publish')
			->with($event);
		$this->invokePrivate($this->activityManager, 'sendToUsers', [$event]);
	}

	public function dataFindObjectForEntity() {
		$board = new Board();
		$board->setId(1);
		$stack = new Stack();
		$stack->setBoardId(1);
		$card = new Card();
		$card->setId(3);
		$attachment = new Attachment();
		$attachment->setCardId(3);
		$label = new Label();
		$label->setCardId(3);
		$label->setBoardId(1);
		$assignedUser = new Assignment();
		$assignedUser->setCardId(3);

		return [
			[ActivityManager::DECK_OBJECT_BOARD, $board],
			[ActivityManager::DECK_OBJECT_BOARD, $stack],
			[ActivityManager::DECK_OBJECT_BOARD, $label],
			[ActivityManager::DECK_OBJECT_CARD, $card],
			[ActivityManager::DECK_OBJECT_CARD, $attachment],
			[ActivityManager::DECK_OBJECT_CARD, $assignedUser],
			[ActivityManager::DECK_OBJECT_CARD, $label],
		];
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @dataProvider dataFindObjectForEntity
	 */
	public function testFindObjectForEntity($objectType, $entity) {
		$board = new Board();
		$board->setId(1);
		$card = new Card();
		$card->setId(3);
		$expected = null;
		if ($objectType === ActivityManager::DECK_OBJECT_BOARD) {
			$this->boardMapper->expects(self::once())
				->method('find')
				->with(1)
				->willReturn($board);
			$expected = $board;
		}
		if ($objectType === ActivityManager::DECK_OBJECT_CARD) {
			$this->cardMapper->expects(self::once())
				->method('find')
				->with(3)
				->willReturn($card);
			$expected = $card;
		}
		$actual = $this->invokePrivate($this->activityManager, 'findObjectForEntity', [$objectType, $entity]);
		$this->assertEquals($expected, $actual);
	}

	public function testFindDetailsForStack() {
		$stack = new Stack();
		$stack->setId(123);
		$stack->setBoardId(999);
		$board = new Board();
		$board->setId(999);
		$this->stackMapper->expects(self::once())
			->method('find')
			->with(123)
			->willReturn($stack);
		$this->boardMapper->expects(self::once())->method('find')
			->with(999)
			->willReturn($board);
		$this->assertEquals([
			'stack' => $stack,
			'board' => $board
		], $this->invokePrivate($this->activityManager, 'findDetailsForStack', [123]));
	}


	public function testFindDetailsForCard() {
		$card = new Card();
		$card->setId(555);
		$card->setStackId(123);
		$stack = new Stack();
		$stack->setId(123);
		$stack->setBoardId(999);
		$board = new Board();
		$board->setId(999);
		$this->cardMapper->expects(self::once())
			->method('find')
			->with(555)
			->willReturn($card);
		$this->stackMapper->expects(self::once())
			->method('find')
			->with(123)
			->willReturn($stack);
		$this->boardMapper->expects(self::once())->method('find')
			->with(999)
			->willReturn($board);
		$this->assertEquals([
			'stack' => $stack,
			'board' => $board,
			'card' => [
				'id' => $card->getId(),
				'title' => $card->getTitle(),
				'archived' => $card->getArchived()
			]
		], $this->invokePrivate($this->activityManager, 'findDetailsForCard', [555]));
	}

	public function testFindDetailsForAttachment() {
		$attachment = new Attachment();
		$attachment->setId(777);
		$attachment->setCardId(555);
		$card = new Card();
		$card->setId(555);
		$card->setStackId(123);
		$stack = new Stack();
		$stack->setId(123);
		$stack->setBoardId(999);
		$board = new Board();
		$board->setId(999);
		$this->cardMapper->expects(self::once())
			->method('find')
			->with(555)
			->willReturn($card);
		$this->stackMapper->expects(self::once())
			->method('find')
			->with(123)
			->willReturn($stack);
		$this->boardMapper->expects(self::once())->method('find')
			->with(999)
			->willReturn($board);
		$this->assertEquals([
			'stack' => $stack,
			'board' => $board,
			'card' => [
				'id' => $card->getId(),
				'title' => $card->getTitle(),
				'archived' => $card->getArchived()
			],
			'attachment' => $attachment
		], $this->invokePrivate($this->activityManager, 'findDetailsForAttachment', [$attachment]));
	}

	public function invokePrivate(&$object, $methodName, array $parameters = []) {
		$reflection = new \ReflectionClass(get_class($object));
		$method = $reflection->getMethod($methodName);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $parameters);
	}
}
