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

namespace OCA\Deck\Notification;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\User;
use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\PermissionService;
use OCP\Comments\IComment;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;

class DummyUser extends \OC\User\User {
	private $uid;

	public function __construct($uid) {
		$this->uid = $uid;
	}

	public function getUID() {
		return $this->uid;
	}
}

class NotificationHelperTest extends \Test\TestCase {

	/** @var CardMapper|MockObject */
	protected $cardMapper;
	/** @var BoardMapper|MockObject */
	protected $boardMapper;
	/** @var AssignmentMapper|MockObject  */
	protected $assignedUsersMapper;
	/** @var PermissionService|MockObject */
	protected $permissionService;
	/** @var IConfig|MockObject */
	protected $config;
	/** @var IManager|MockObject */
	protected $notificationManager;
	/** @var IGroupManager|MockObject */
	protected $groupManager;
	/** @var string */
	protected $currentUser;
	/** @var NotificationHelper */
	protected $notificationHelper;

	public function setUp(): void {
		parent::setUp();
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->assignedUsersMapper = $this->createMock(AssignmentMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->currentUser = 'admin';
		$this->notificationHelper = new NotificationHelper(
			$this->cardMapper,
			$this->boardMapper,
			$this->assignedUsersMapper,
			$this->permissionService,
			$this->config,
			$this->notificationManager,
			$this->groupManager,
			$this->currentUser
		);
	}

	public function testSendCardDuedateAlreadyNotified() {
		$card = $this->createMock(Card::class);
		$card->expects($this->once())
			->method('__call')
			->with('getNotified', [])
			->willReturn(true);
		$this->notificationHelper->sendCardDuedate($card);
	}

	private function createUserMock($uid) {
		$user = $this->createMock(User::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn($uid);
		return $user;
	}

	public function testSendCardDuedate() {
		$param1 = ['foo', 'bar', 'asd'];
		$param2 = 'deck';
		$param3 = 'board:234:notify-due';
		$DUE_ASSIGNED = ConfigService::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED;

		$this->config->expects($this->exactly(3))
			->method('getUserValue')
			->withConsecutive(
				[$param1[0], $param2, $param3, $DUE_ASSIGNED],
				[$param1[1], $param2, $param3, $DUE_ASSIGNED],
				[$param1[2], $param2, $param3, $DUE_ASSIGNED],
			)
			->willReturn(ConfigService::SETTING_BOARD_NOTIFICATION_DUE_ALL);

		$card = Card::fromParams([
			'notified' => false,
			'id' => 123,
			'title' => 'MyCardTitle',
			'duedate' => '2020-12-24'
		]);
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->with(123)
			->willReturn(234);
		$board = Board::fromParams([
			'id' => 123,
			'title' => 'MyBoardTitle'
		]);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(234)
			->willReturn($board);

		$users = [
			$this->createUserMock('foo'),
			$this->createUserMock('bar'),
			$this->createUserMock('asd')

		];
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->with(234)
			->willReturn($users);


		$n1 = $this->createMock(INotification::class);
		$n2 = $this->createMock(INotification::class);
		$n3 = $this->createMock(INotification::class);

		$n1->expects($this->once())->method('setApp')->with('deck')->willReturn($n1);
		$n1->expects($this->once())->method('setUser')->with('foo')->willReturn($n1);
		$n1->expects($this->once())->method('setObject')->with('card', 123)->willReturn($n1);
		$n1->expects($this->once())->method('setSubject')->with('card-overdue', ['MyCardTitle', 'MyBoardTitle'])->willReturn($n1);
		$n1->expects($this->once())->method('setDateTime')->willReturn($n1);

		$n2->expects($this->once())->method('setApp')->with('deck')->willReturn($n2);
		$n2->expects($this->once())->method('setUser')->with('bar')->willReturn($n2);
		$n2->expects($this->once())->method('setObject')->with('card', 123)->willReturn($n2);
		$n2->expects($this->once())->method('setSubject')->with('card-overdue', ['MyCardTitle', 'MyBoardTitle'])->willReturn($n2);
		$n2->expects($this->once())->method('setDateTime')->willReturn($n2);

		$n3->expects($this->once())->method('setApp')->with('deck')->willReturn($n3);
		$n3->expects($this->once())->method('setUser')->with('asd')->willReturn($n3);
		$n3->expects($this->once())->method('setObject')->with('card', 123)->willReturn($n3);
		$n3->expects($this->once())->method('setSubject')->with('card-overdue', ['MyCardTitle', 'MyBoardTitle'])->willReturn($n3);
		$n3->expects($this->once())->method('setDateTime')->willReturn($n3);

		$this->notificationManager->expects($this->exactly(3))
			->method('createNotification')
			->willReturnOnConsecutiveCalls($n1, $n2, $n3);
		$this->notificationManager->expects($this->exactly(3))
			->method('notify')
			->withConsecutive([$n1], [$n2], [$n3]);

		$this->cardMapper->expects($this->once())
			->method('markNotified')
			->with($card);

		$this->notificationHelper->sendCardDuedate($card);
	}

	public function testSendCardDuedateAssigned() {
		$param1 = ['foo', 'bar', 'asd'];
		$param2 = 'deck';
		$param3 = 'board:234:notify-due';
		$DUE_ASSIGNED = ConfigService::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED;

		$this->config->expects($this->exactly(3))
			->method('getUserValue')
			->withConsecutive(
				[$param1[0], $param2, $param3, $DUE_ASSIGNED],
				[$param1[1], $param2, $param3, $DUE_ASSIGNED],
				[$param1[2], $param2, $param3, $DUE_ASSIGNED]
			)
			->willReturn($DUE_ASSIGNED);

		$users = [
			new DummyUser('foo'), new DummyUser('bar'), new DummyUser('asd')
		];
		$card = Card::fromParams([
			'notified' => false,
			'id' => 123,
			'title' => 'MyCardTitle',
			'duedate' => '2020-12-24'
		]);
		$card->setAssignedUsers([
			new User($users[0])
		]);
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->with(123)
			->willReturn(234);
		$board = Board::fromParams([
			'id' => 123,
			'title' => 'MyBoardTitle'
		]);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(234)
			->willReturn($board);


		$this->permissionService->expects($this->once())
			->method('findUsers')
			->with(234)
			->willReturn($users);

		$this->assignedUsersMapper->expects($this->exactly(3))
			->method('isUserAssigned')
			->willReturn(true);


		$n1 = $this->createMock(INotification::class);
		$n2 = $this->createMock(INotification::class);
		$n3 = $this->createMock(INotification::class);

		$n1->expects($this->once())->method('setApp')->with('deck')->willReturn($n1);
		$n1->expects($this->once())->method('setUser')->with('foo')->willReturn($n1);
		$n1->expects($this->once())->method('setObject')->with('card', 123)->willReturn($n1);
		$n1->expects($this->once())->method('setSubject')->with('card-overdue', ['MyCardTitle', 'MyBoardTitle'])->willReturn($n1);
		$n1->expects($this->once())->method('setDateTime')->willReturn($n1);

		$n2->expects($this->once())->method('setApp')->with('deck')->willReturn($n2);
		$n2->expects($this->once())->method('setUser')->with('bar')->willReturn($n2);
		$n2->expects($this->once())->method('setObject')->with('card', 123)->willReturn($n2);
		$n2->expects($this->once())->method('setSubject')->with('card-overdue', ['MyCardTitle', 'MyBoardTitle'])->willReturn($n2);
		$n2->expects($this->once())->method('setDateTime')->willReturn($n2);

		$n3->expects($this->once())->method('setApp')->with('deck')->willReturn($n3);
		$n3->expects($this->once())->method('setUser')->with('asd')->willReturn($n3);
		$n3->expects($this->once())->method('setObject')->with('card', 123)->willReturn($n3);
		$n3->expects($this->once())->method('setSubject')->with('card-overdue', ['MyCardTitle', 'MyBoardTitle'])->willReturn($n3);
		$n3->expects($this->once())->method('setDateTime')->willReturn($n3);

		$this->notificationManager->expects($this->exactly(3))
			->method('createNotification')
			->willReturnOnConsecutiveCalls($n1, $n2, $n3);
		$this->notificationManager->expects($this->exactly(3))
			->method('notify')
			->withConsecutive([$n1], [$n2], [$n3]);

		$this->cardMapper->expects($this->once())
			->method('markNotified')
			->with($card);

		$this->notificationHelper->sendCardDuedate($card);
	}


	public function testSendCardDuedateNever() {
		$param1 = ['foo', 'bar', 'asd'];
		$param2 = 'deck';
		$param3 = 'board:234:notify-due';
		$DUE_ASSIGNED = ConfigService::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED;
		$DUE_OFF = ConfigService::SETTING_BOARD_NOTIFICATION_DUE_OFF;

		$this->config->expects($this->exactly(3))
			->method('getUserValue')
			->withConsecutive(
				[$param1[0], $param2, $param3, $DUE_ASSIGNED],
				[$param1[1], $param2, $param3, $DUE_ASSIGNED],
				[$param1[2], $param2, $param3, $DUE_ASSIGNED]
			)
			->willReturnOnConsecutiveCalls($DUE_ASSIGNED, $DUE_ASSIGNED, $DUE_OFF);

		$users = [
			new DummyUser('foo'), new DummyUser('bar'), new DummyUser('asd')
		];
		$card = Card::fromParams([
			'notified' => false,
			'id' => 123,
			'title' => 'MyCardTitle',
			'duedate' => '2020-12-24'
		]);
		$card->setAssignedUsers([
			new User($users[0])
		]);
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->with(123)
			->willReturn(234);
		$board = Board::fromParams([
			'id' => 123,
			'title' => 'MyBoardTitle'
		]);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(234)
			->willReturn($board);


		$this->permissionService->expects($this->once())
			->method('findUsers')
			->with(234)
			->willReturn($users);

		$this->assignedUsersMapper->expects($this->exactly(2))
			->method('isUserAssigned')
			->willReturn(true);


		$n1 = $this->createMock(INotification::class);
		$n2 = $this->createMock(INotification::class);

		$n1->expects($this->once())->method('setApp')->with('deck')->willReturn($n1);
		$n1->expects($this->once())->method('setUser')->with('foo')->willReturn($n1);
		$n1->expects($this->once())->method('setObject')->with('card', 123)->willReturn($n1);
		$n1->expects($this->once())->method('setSubject')->with('card-overdue', ['MyCardTitle', 'MyBoardTitle'])->willReturn($n1);
		$n1->expects($this->once())->method('setDateTime')->willReturn($n1);

		$n2->expects($this->once())->method('setApp')->with('deck')->willReturn($n2);
		$n2->expects($this->once())->method('setUser')->with('bar')->willReturn($n2);
		$n2->expects($this->once())->method('setObject')->with('card', 123)->willReturn($n2);
		$n2->expects($this->once())->method('setSubject')->with('card-overdue', ['MyCardTitle', 'MyBoardTitle'])->willReturn($n2);
		$n2->expects($this->once())->method('setDateTime')->willReturn($n2);

		$this->notificationManager->expects($this->exactly(2))
			->method('createNotification')
			->willReturnOnConsecutiveCalls($n1, $n2);
		$this->notificationManager->expects($this->exactly(2))
			->method('notify')
			->withConsecutive([$n1], [$n2]);

		$this->cardMapper->expects($this->once())
			->method('markNotified')
			->with($card);

		$this->notificationHelper->sendCardDuedate($card);
	}

	public function testSendCardAssignedUser() {
		$board = new Board();
		$board->setId(123);
		$board->setTitle('MyBoardTitle');
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($board);

		$acl = new Acl();
		$acl->setParticipant('admin');
		$acl->setType(Acl::PERMISSION_TYPE_USER);

		$card = new Card();
		$card->setTitle('MyCardTitle');
		$card->setOwner('admin');
		$card->setStackId(123);
		$card->setOrder(999);
		$card->setType('text');
		$card->setId(1337);
		$card->setAssignedUsers(['userA']);
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->with(1337)
			->willReturn(123);

		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())->method('setApp')->with('deck')->willReturn($notification);
		$notification->expects($this->once())->method('setUser')->with('userA')->willReturn($notification);
		$notification->expects($this->once())->method('setObject')->with('card', 1337)->willReturn($notification);
		$notification->expects($this->once())->method('setSubject')->with('card-assigned', ['MyCardTitle', 'MyBoardTitle', 'admin'])->willReturn($notification);
		$notification->expects($this->once())->method('setDateTime')->willReturn($notification);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notificationHelper->sendCardAssigned($card, 'userA');
	}

	public function testSendBoardSharedUser() {
		$board = new Board();
		$board->setId(123);
		$board->setTitle('MyBoardTitle');
		$acl = new Acl();
		$acl->setParticipant('userA');
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($board);
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())->method('setApp')->with('deck')->willReturn($notification);
		$notification->expects($this->once())->method('setUser')->with('userA')->willReturn($notification);
		$notification->expects($this->once())->method('setObject')->with('board', 123)->willReturn($notification);
		$notification->expects($this->once())->method('setSubject')->with('board-shared', ['MyBoardTitle', 'admin'])->willReturn($notification);
		$notification->expects($this->once())->method('setDateTime')->willReturn($notification);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notificationHelper->sendBoardShared(123, $acl);
	}

	public function testSendBoardSharedGroup() {
		$board = new Board();
		$board->setId(123);
		$board->setTitle('MyBoardTitle');
		$acl = new Acl();
		$acl->setParticipant('groupA');
		$acl->setType(Acl::PERMISSION_TYPE_GROUP);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($board);
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('userA');
		$group = $this->createMock(IGroup::class);
		$group->expects($this->once())
			->method('getUsers')
			->willReturn([$user]);
		$this->groupManager->expects($this->once())
			->method('get')
			->willReturn($group);
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())->method('setApp')->with('deck')->willReturn($notification);
		$notification->expects($this->once())->method('setUser')->with('userA')->willReturn($notification);
		$notification->expects($this->once())->method('setObject')->with('board', 123)->willReturn($notification);
		$notification->expects($this->once())->method('setSubject')->with('board-shared', ['MyBoardTitle', 'admin'])->willReturn($notification);
		$notification->expects($this->once())->method('setDateTime')->willReturn($notification);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->notificationHelper->sendBoardShared(123, $acl);
	}

	public function testSendMention() {
		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())
			->method('getObjectId')
			->willReturn(123);
		$comment->expects($this->any())
			->method('getMessage')
			->willReturn('@user1 @user2 This is a message.');
		$comment->expects($this->once())
			->method('getMentions')
			->willReturn([
				['id' => 'user1'],
				['id' => 'user2']
			]);
		$card = new Card();
		$card->setId(123);
		$card->setTitle('MyCard');
		$this->cardMapper->expects($this->any())
			->method('find')
			->with(123)
			->willReturn($card);
		$this->cardMapper->expects($this->any())
			->method('findBoardId')
			->with(123)
			->willReturn(1);

		$notification1 = $this->createMock(INotification::class);
		$notification1->expects($this->once())->method('setApp')->with('deck')->willReturn($notification1);
		$notification1->expects($this->once())->method('setUser')->with('user1')->willReturn($notification1);
		$notification1->expects($this->once())->method('setObject')->with('card', 123)->willReturn($notification1);
		$notification1->expects($this->once())->method('setSubject')->with('card-comment-mentioned', ['MyCard', 1, 'admin'])->willReturn($notification1);
		$notification1->expects($this->once())->method('setDateTime')->willReturn($notification1);

		$notification2 = $this->createMock(INotification::class);
		$notification2->expects($this->once())->method('setApp')->with('deck')->willReturn($notification2);
		$notification2->expects($this->once())->method('setUser')->with('user2')->willReturn($notification2);
		$notification2->expects($this->once())->method('setObject')->with('card', 123)->willReturn($notification2);
		$notification2->expects($this->once())->method('setSubject')->with('card-comment-mentioned', ['MyCard', 1, 'admin'])->willReturn($notification2);
		$notification2->expects($this->once())->method('setDateTime')->willReturn($notification2);

		$this->notificationManager->expects($this->exactly(2))
			->method('createNotification')
			->willReturnOnConsecutiveCalls($notification1, $notification2);
		$this->notificationManager->expects($this->exactly(2))
			->method('notify')
			->withConsecutive([$notification1], [$notification2]);

		$this->notificationHelper->sendMention($comment);
	}
}
