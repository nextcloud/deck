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

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\StatusException;
use OCP\Activity\IEvent;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;
use OCP\IURLGenerator;

class CardServiceTest extends TestCase {

	/** @var CardService|MockObject */
	private $cardService;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var PermissionService|MockObject */
	private $permissionService;
	/** @var NotificationHelper */
	private $notificationHelper;
	/** @var AssignmentMapper|MockObject */
	private $assignedUsersMapper;
	/** @var BoardService|MockObject */
	private $boardService;
	/** @var LabelMapper|MockObject */
	private $labelMapper;
	private $boardMapper;
	/** @var AttachmentService|MockObject */
	private $attachmentService;
	/** @var ActivityManager|MockObject */
	private $activityManager;
	/** @var ICommentsManager|MockObject */
	private $commentsManager;
	/** @var ICommentsManager|MockObject */
	private $userManager;
	/** @var EventDispatcherInterface */
	private $eventDispatcher;
	/** @var ChangeHelper|MockObject */
	private $changeHelper;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var IRequest|MockObject */
	private $request;
	/** @var LoggerInterface|MockObject */
	private $logger;

	public function setUp(): void {
		parent::setUp();
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->notificationHelper = $this->createMock(NotificationHelper::class);
		$this->assignedUsersMapper = $this->createMock(AssignmentMapper::class);
		$this->attachmentService = $this->createMock(AttachmentService::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->request = $this->createMock(IRequest::class);

		$this->logger->expects($this->any())->method('error');

		$this->cardService = new CardService(
			$this->cardMapper,
			$this->stackMapper,
			$this->boardMapper,
			$this->labelMapper,
			$this->permissionService,
			$this->boardService,
			$this->notificationHelper,
			$this->assignedUsersMapper,
			$this->attachmentService,
			$this->activityManager,
			$this->commentsManager,
			$this->userManager,
			$this->changeHelper,
			$this->eventDispatcher,
			$this->urlGenerator,
			$this->logger,
			$this->request,
			'user1'
		);
	}

	public function mockActivity($type, $object, $subject) {
		// ActivityManager::DECK_OBJECT_BOARD, $newAcl, ActivityManager::SUBJECT_BOARD_SHARE
		$event = $this->createMock(IEvent::class);
		$this->activityManager->expects($this->once())
			->method('createEvent')
			->with($type, $object, $subject)
			->willReturn($event);
		$this->activityManager->expects($this->once())
			->method('sendToUsers')
			->with($event);
	}

	public function testFind() {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->willReturn($user);
		$this->commentsManager->expects($this->any())
			->method('getNumberOfCommentsForObject')
			->willReturn(0);
		$boardMock = $this->createMock(Board::class);
		$stackMock = $this->createMock(Stack::class);
		$this->stackMapper->expects($this->any())
			->method('find')
			->willReturn($stackMock);
		$this->boardService->expects($this->any())
			->method('find')
			->willReturn($boardMock);
		$card = new Card();
		$card->setId(1337);
		$this->cardMapper->expects($this->any())
			->method('find')
			->with(123)
			->willReturn($card);
		$this->assignedUsersMapper->expects($this->any())
			->method('findAll')
			->with(1337)
			->willReturn(['user1', 'user2']);
		$cardExpected = new Card();
		$cardExpected->setId(1337);
		$cardExpected->setAssignedUsers(['user1', 'user2']);
		$cardExpected->setRelatedBoard($boardMock);
		$cardExpected->setRelatedStack($stackMock);
		$cardExpected->setLabels([]);
		$this->assertEquals($cardExpected, $this->cardService->find(123));
	}

	public function testCreate() {
		$card = new Card();
		$card->setTitle('Card title');
		$card->setOwner('admin');
		$card->setStackId(123);
		$card->setOrder(999);
		$card->setType('text');
		$this->cardMapper->expects($this->once())
			->method('insert')
			->willReturn($card);
		$b = $this->cardService->create('Card title', 123, 'text', 999, 'admin');

		$this->assertEquals($b->getTitle(), 'Card title');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getType(), 'text');
		$this->assertEquals($b->getOrder(), 999);
		$this->assertEquals($b->getStackId(), 123);
	}

	public function testDelete() {
		$cardToBeDeleted = new Card();
		$this->cardMapper->expects($this->once())
			->method('find')
			->willReturn($cardToBeDeleted);
		$this->cardMapper->expects($this->once())
			->method('update')
			->willReturn($cardToBeDeleted);
		$this->cardService->delete(123);
		$this->assertTrue($cardToBeDeleted->getDeletedAt() <= time(), 'deletedAt is in the past');
	}

	public function testUpdate() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(false);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$actual = $this->cardService->update(123, 'newtitle', 234, 'text', 'admin', 'foo', 999, '2017-01-01 00:00:00', null);
		$this->assertEquals('newtitle', $actual->getTitle());
		$this->assertEquals(234, $actual->getStackId());
		$this->assertEquals('text', $actual->getType());
		$this->assertEquals(999, $actual->getOrder());
		$this->assertEquals('foo', $actual->getDescription());
		$this->assertEquals('2017-01-01T00:00:00+00:00', $actual->getDuedate());
	}

	public function testUpdateArchived() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(true);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('update');
		$this->expectException(StatusException::class);
		$this->cardService->update(123, 'newtitle', 234, 'text', 'admin', 'foo', 999, '2017-01-01 00:00:00', null, true);
	}

	public function testRename() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(false);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$actual = $this->cardService->rename(123, 'newtitle');
		$this->assertEquals('newtitle', $actual->getTitle());
	}

	public function testRenameArchived() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(true);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('update');
		$this->expectException(StatusException::class);
		$this->cardService->rename(123, 'newtitle');
	}

	public function dataReorder() {
		return [
			[0, 0, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]],
			[0, 9, [1, 2, 3, 4, 5, 6, 7, 8, 9, 0]],
			[1, 3, [0, 2, 3, 1, 4, 5, 6, 7, 8, 9]]
		];
	}
	/** @dataProvider dataReorder */
	public function testReorder($cardId, $newPosition, $order) {
		$cards = $this->getCards();
		$cardsTmp = [];
		$this->cardMapper->expects($this->once())->method('findAll')->willReturn($cards);
		$card = new Card();
		$card->setStackId(123);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$result = $this->cardService->reorder($cardId, 123, $newPosition);
		foreach ($result as $card) {
			$actual[$card->getOrder()] = $card->getId();
		}
		$this->assertEquals($order, $actual);
	}

	private function getCards() {
		$cards = [];
		for ($i = 0; $i < 10; $i++) {
			$cards[$i] = new Card();
			$cards[$i]->setTitle($i);
			$cards[$i]->setOrder($i);
			$cards[$i]->setId($i);
		}
		return $cards;
	}

	public function testReorderArchived() {
		$card = new Card();
		$card->setTitle('title');
		$card->setArchived(true);
		$card->setStackId(123);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$this->expectException(StatusException::class);
		$actual = $this->cardService->reorder(123, 234, 1);
	}
	public function testArchive() {
		$card = new Card();
		$this->assertFalse($card->getArchived());
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$this->assertTrue($this->cardService->archive(123)->getArchived());
	}
	public function testUnarchive() {
		$card = new Card();
		$card->setArchived(true);
		$this->assertTrue($card->getArchived());
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function ($c) {
			return $c;
		});
		$this->assertFalse($this->cardService->unarchive(123)->getArchived());
	}

	public function testAssignLabel() {
		$card = new Card();
		$card->setArchived(false);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('assignLabel');
		$this->cardService->assignLabel(123, 999);
	}

	public function testAssignLabelArchived() {
		$card = new Card();
		$card->setArchived(true);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('assignLabel');
		$this->expectException(StatusException::class);
		$this->cardService->assignLabel(123, 999);
	}

	public function testRemoveLabel() {
		$card = new Card();
		$card->setArchived(false);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->once())->method('removeLabel');
		$this->cardService->removeLabel(123, 999);
	}

	public function testRemoveLabelArchived() {
		$card = new Card();
		$card->setArchived(true);
		$this->cardMapper->expects($this->once())->method('find')->willReturn($card);
		$this->cardMapper->expects($this->never())->method('removeLabel');
		$this->expectException(StatusException::class);
		$this->cardService->removeLabel(123, 999);
	}
}
