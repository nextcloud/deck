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
use OCA\Deck\Db\AttachmentMapper;
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
use PHPUnit_Framework_MockObject_MockObject as MockObject;

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
	/** @var AttachmentMapper|MockObject */
	private $attachmentMapper;
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
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->activityManager = new ActivityManager(
			$this->manager,
			$this->permissionService,
			$this->boardMapper,
			$this->cardMapper,
			$this->stackMapper,
			$this->attachmentMapper,
			$this->aclMapper,
			$this->l10nFactory,
			$this->userId
		);
	}

	public function testGetActivityFormatOwn() {
		$managerClass = new \ReflectionClass(ActivityManager::class);
		$this->l10n->expects($this->any())
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
					/** @noinspection ForgottenDebugOutputInspection */
					print_r('No activity string found for '. $constant . PHP_EOL);
				}
				$format = $this->activityManager->getActivityFormat('cz', $value, [], true);
				if ($format !== '') {
					$this->assertStringStartsWith('You', $format);
				} else {
					/** @noinspection ForgottenDebugOutputInspection */
					print_r('No own activity string found for '. $constant . PHP_EOL);
				}
			}
		}
	}

	public function testCreateEvent() {
		$board = new Board();
		$board->setId(123);
		$board->setTitle('');
		$this->boardMapper->expects($this->once())
			->method('find')
			->willReturn($board);
		$event = $this->createMock(IEvent::class);
		$this->manager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);
		$event->expects($this->once())->method('setApp')->willReturn($event);
		$event->expects($this->once())->method('setType')->willReturn($event);
		$event->expects($this->once())->method('setAuthor')->willReturn($event);
		$event->expects($this->once())->method('setObject')->willReturn($event);
		$event->expects($this->once())->method('setSubject')->willReturn($event);
		$event->expects($this->once())->method('setTimestamp')->willReturn($event);
		$actual = $this->invokePrivate($this->activityManager, 'createEvent', [
			ActivityManager::DECK_OBJECT_BOARD,
			$board,
			ActivityManager::SUBJECT_BOARD_CREATE
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
		$user->expects($this->any())
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
		$event->expects($this->once())
			->method('getObjectType')
			->willReturn($objectType);
		$event->expects($this->once())
			->method('getObjectId')
			->willReturn(1);
		$event->expects($this->exactly(2))
			->method('setAffectedUser')
			->withConsecutive(['user1'], ['user2']);
		$mapper = null;
		switch ($objectType) {
			case ActivityManager::DECK_OBJECT_BOARD:
				$mapper = $this->boardMapper;
				break;
			case ActivityManager::DECK_OBJECT_CARD:
				$mapper = $this->cardMapper;
				break;
		}
		$mapper->expects($this->once())
			->method('findBoardId')
			->willReturn(123);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn($users);
		$this->manager->expects($this->exactly(2))
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
			$this->boardMapper->expects($this->once())
				->method('find')
				->with(1)
				->willReturn($board);
			$expected = $board;
		}
		if ($objectType === ActivityManager::DECK_OBJECT_CARD) {
			$this->cardMapper->expects($this->once())
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
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($stack);
		$this->boardMapper->expects($this->once())->method('find')
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
		$this->cardMapper->expects($this->once())
			->method('find')
			->with(555)
			->willReturn($card);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($stack);
		$this->boardMapper->expects($this->once())->method('find')
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
		$this->cardMapper->expects($this->once())
			->method('find')
			->with(555)
			->willReturn($card);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($stack);
		$this->boardMapper->expects($this->once())->method('find')
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
