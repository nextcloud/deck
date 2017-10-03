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
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\User;
use OCA\Deck\Service\PermissionService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

class NotificationHelperTest extends \Test\TestCase {

	/** @var CardMapper */
	protected $cardMapper;
	/** @var BoardMapper */
	protected $boardMapper;
	/** @var PermissionService */
	protected $permissionService;
	/** @var IManager */
	protected $notificationManager;
	/** @var IGroupManager */
	protected $groupManager;
	/** @var string */
	protected $currentUser;
	/** @var NotificationHelper */
	protected $notificationHelper;

	public function setUp() {
		parent::setUp();
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->currentUser = 'admin';
		$this->notificationHelper = new NotificationHelper(
			$this->cardMapper,
			$this->boardMapper,
			$this->permissionService,
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
		$card = $this->createMock(Card::class);
		$card->expects($this->at(0))
			->method('__call')
			->with('getNotified', [])
			->willReturn(false);
		$card->expects($this->at(1))
			->method('__call')
			->with('getId', [])
			->willReturn(123);
		for($i=0; $i<3; $i++) {
			$card->expects($this->at($i*3+2))
				->method('__call')
				->with('getId', [])
				->willReturn(123);
			$card->expects($this->at($i*3+3))
				->method('__call', [])
				->with('getTitle')
				->willReturn('MyCardTitle');
		}
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->with(123)
			->willReturn(234);
		$board = $this->createMock(Board::class);
		$board->expects($this->any())
			->method('__call')
			->with('getTitle', [])
			->willReturn('MyBoardTitle');
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

		$this->notificationManager->expects($this->at(0))
			->method('createNotification')
			->willReturn($n1);
		$this->notificationManager->expects($this->at(1))
			->method('notify')
			->with($n1);
		$this->notificationManager->expects($this->at(2))
			->method('createNotification')
			->willReturn($n2);
		$this->notificationManager->expects($this->at(3))
			->method('notify')
			->with($n2);
		$this->notificationManager->expects($this->at(4))
			->method('createNotification')
			->willReturn($n3);
		$this->notificationManager->expects($this->at(5))
			->method('notify')
			->with($n3);

		$this->cardMapper->expects($this->once())
			->method('markNotified')
			->with($card);

		$this->notificationHelper->sendCardDuedate($card);
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

		$this->notificationManager->expects($this->at(0))
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->at(1))
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
		$user->expects($this->once())
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

		$this->notificationManager->expects($this->at(0))
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->at(1))
			->method('notify')
			->with($notification);

		$this->notificationHelper->sendBoardShared(123, $acl);
	}


}