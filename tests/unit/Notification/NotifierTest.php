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

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\RichObjectStrings\Definitions;


class NotifierTest extends \Test\TestCase {

	/** @var IFactory */
	protected $l10nFactory;
	/** @var IURLGenerator */
	protected $url;
	/** @var IUserManager */
	protected $userManager;
	/** @var CardMapper */
	protected $cardMapper;
	/** @var BoardMapper */
	protected $boardMapper;
	/** @var Notifier */
	protected $notifier;
	/** @var IL10N */
	protected $l10n;

	public function setUp() {
		parent::setUp();
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->notifier = new Notifier(
			$this->l10nFactory,
			$this->url,
			$this->userManager,
			$this->cardMapper,
			$this->boardMapper
		);
		$this->l10n = \OC::$server->getL10N('deck');
		$this->l10nFactory->expects($this->once())
			->method('get')
			->willReturn($this->l10n);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testPrepareWrongApp() {
		/** @var INotification $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('files');

		$this->notifier->prepare($notification, 'en_US');
	}

	public function testPrepareCardOverdue() {
		/** @var INotification $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('deck');

		$notification->expects($this->once())
			->method('getSubjectParameters')
			->willReturn(['Card title','Board title']);

		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('card-overdue');
		$notification->expects($this->once())
			->method('getObjectId')
			->willReturn('123');
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->willReturn(999);
		$expectedMessage = 'The card "Card title" on "Board title" has reached its due date.';
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($expectedMessage);


		$this->url->expects($this->once())
			->method('imagePath')
			->with('deck', 'deck-dark.svg')
			->willReturn('deck-dark.svg');
		$this->url->expects($this->once())
			->method('getAbsoluteURL')
			->with('deck-dark.svg')
			->willReturn('/absolute/deck-dark.svg');
		$notification->expects($this->once())
			->method('setIcon')
			->with('/absolute/deck-dark.svg');

		$actualNotification = $this->notifier->prepare($notification, 'en_US');

		$this->assertEquals($notification, $actualNotification);

	}

	public function testPrepareCardCommentMentioned() {
		/** @var INotification $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('deck');

		$notification->expects($this->once())
			->method('getSubjectParameters')
			->willReturn(['Card title', 'Board title', 'admin']);

		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('card-comment-mentioned');
		$notification->expects($this->once())
			->method('getObjectId')
			->willReturn('123');
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->willReturn(999);
		$expectedMessage = 'admin has mentioned you in a comment on "Card title".';
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($expectedMessage);
		$notification->expects($this->once())
			->method('setRichSubject')
			->with('{user} has mentioned you in a comment on "Card title".');


		$this->url->expects($this->once())
			->method('imagePath')
			->with('deck', 'deck-dark.svg')
			->willReturn('deck-dark.svg');
		$this->url->expects($this->once())
			->method('getAbsoluteURL')
			->with('deck-dark.svg')
			->willReturn('/absolute/deck-dark.svg');
		$notification->expects($this->once())
			->method('setIcon')
			->with('/absolute/deck-dark.svg');

		$actualNotification = $this->notifier->prepare($notification, 'en_US');

		$this->assertEquals($notification, $actualNotification);

	}

	public function dataPrepareCardAssigned() {
		return [
			[true], [false]
		];
	}

	/** @dataProvider dataPrepareCardAssigned */
	public function testPrepareCardAssigned($withUserFound = true) {
		/** @var INotification $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('deck');

		$notification->expects($this->once())
			->method('getSubjectParameters')
			->willReturn(['Card title','Board title', 'otheruser']);

		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('card-assigned');
		$notification->expects($this->once())
			->method('getObjectId')
			->willReturn('123');
		if ($withUserFound) {
			$user = $this->createMock(IUser::class);
			$user->expects($this->any())
				->method('getDisplayName')
				->willReturn('Other User');
			$dn = 'Other User';
		} else {
			$user = null;
			$dn = 'otheruser';
		}
		$this->userManager->expects($this->once())
			->method('get')
			->with('otheruser')
			->willReturn($user);

		$expectedMessage = 'The card "Card title" on "Board title" has been assigned to you by '.$dn.'.';
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($expectedMessage);
		$notification->expects($this->once())
			->method('setRichSubject')
			->with('{user} has assigned the card "Card title" on "Board title" to you.', [
				'user' => [
					'type' => 'user',
					'id' => 'otheruser',
					'name' => $dn,
				]
			]);

		$this->url->expects($this->once())
			->method('imagePath')
			->with('deck', 'deck-dark.svg')
			->willReturn('deck-dark.svg');
		$this->url->expects($this->once())
			->method('getAbsoluteURL')
			->with('deck-dark.svg')
			->willReturn('/absolute/deck-dark.svg');
		$notification->expects($this->once())
			->method('setIcon')
			->with('/absolute/deck-dark.svg');

		$actualNotification = $this->notifier->prepare($notification, 'en_US');

		$this->assertEquals($notification, $actualNotification);
	}

	public function dataPrepareBoardShared() {
		return [
			[true], [false]
		];
	}

	/** @dataProvider dataPrepareBoardShared */
	public function testPrepareBoardShared($withUserFound = true) {
		/** @var INotification $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('deck');

		$notification->expects($this->once())
			->method('getSubjectParameters')
			->willReturn(['Board title', 'otheruser']);

		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('board-shared');
		$notification->expects($this->once())
			->method('getObjectId')
			->willReturn('123');
		if ($withUserFound) {
			$user = $this->createMock(IUser::class);
			$user->expects($this->any())
				->method('getDisplayName')
				->willReturn('Other User');
			$dn = 'Other User';
		} else {
			$user = null;
			$dn = 'otheruser';
		}
		$this->userManager->expects($this->once())
			->method('get')
			->with('otheruser')
			->willReturn($user);

		$expectedMessage = 'The board "Board title" has been shared with you by '.$dn.'.';
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($expectedMessage);
		$notification->expects($this->once())
			->method('setRichSubject')
			->with('{user} has shared the board Board title with you.', [
				'user' => [
					'type' => 'user',
					'id' => 'otheruser',
					'name' => $dn,
				]
			]);

		$this->url->expects($this->once())
			->method('imagePath')
			->with('deck', 'deck-dark.svg')
			->willReturn('deck-dark.svg');
		$this->url->expects($this->once())
			->method('getAbsoluteURL')
			->with('deck-dark.svg')
			->willReturn('/absolute/deck-dark.svg');
		$notification->expects($this->once())
			->method('setIcon')
			->with('/absolute/deck-dark.svg');

		$actualNotification = $this->notifier->prepare($notification, 'en_US');

		$this->assertEquals($notification, $actualNotification);
	}

}
