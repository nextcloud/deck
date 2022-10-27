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
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;

class NotifierTest extends \Test\TestCase {

	/** @var IFactory|MockObject */
	protected $l10nFactory;
	/** @var IURLGenerator|MockObject */
	protected $url;
	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var CardMapper|MockObject */
	protected $cardMapper;
	/** @var StackMapper|MockObject */
	protected $stackMapper;
	/** @var BoardMapper */
	protected $boardMapper;
	/** @var IL10N|MockObject */
	protected $l10n;
	/** @var Notifier */
	protected $notifier;

	public function setUp(): void {
		parent::setUp();
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->notifier = new Notifier(
			$this->l10nFactory,
			$this->url,
			$this->userManager,
			$this->cardMapper,
			$this->stackMapper,
			$this->boardMapper
		);
		$this->l10n = Server::get(IFactory::class)->get('deck');
		$this->l10nFactory->expects($this->once())
			->method('get')
			->willReturn($this->l10n);
	}

	public function testPrepareWrongApp() {
		$this->expectException(\InvalidArgumentException::class);
		/** @var INotification|MockObject $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('files');

		$this->notifier->prepare($notification, 'en_US');
	}

	public function testPrepareCardOverdue() {
		/** @var INotification|MockObject $notification */
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
		$this->stackMapper->expects($this->once())
			->method('findStackFromCardId')
			->willReturn($this->buildMockStack());
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
		/** @var INotification|MockObject $notification */
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
		$this->stackMapper->expects($this->once())
			->method('findStackFromCardId')
			->willReturn($this->buildMockStack());
		$expectedMessage = 'admin has mentioned you in a comment on "Card title".';
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($expectedMessage);
		$notification->expects($this->once())
			->method('setRichSubject')
			->with('{user} has mentioned you in a comment on {deck-card}.');


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
		$this->stackMapper->expects($this->once())
			->method('findStackFromCardId')
			->willReturn($this->buildMockStack(123));

		/** @var INotification|MockObject $notification */
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
			->with('{user} has assigned the card {deck-card} on {deck-board} to you.', [
				'user' => [
					'type' => 'user',
					'id' => 'otheruser',
					'name' => $dn,
				],
				'deck-card' => [
					'type' => 'deck-card',
					'id' => '123',
					'name' => 'Card title',
					'boardname' => 'Board title',
					'stackname' => null,
					'link' => '#/board/123/card/123',
				],
				'deck-board' => [
					'type' => 'deck-board',
					'id' => 123,
					'name' => 'Board title',
					'link' => '#/board/123',
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
		/** @var INotification|MockObject $notification */
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
			->with('{user} has shared {deck-board} with you.', [
				'user' => [
					'type' => 'user',
					'id' => 'otheruser',
					'name' => $dn,
				],
				'deck-board' => [
					'type' => 'deck-board',
					'id' => 123,
					'name' => 'Board title',
					'link' => '#/board/123',
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

	/**
	 * @param int $boardId
	 * @return Stack|MockObject
	 */
	private function buildMockStack(int $boardId = 999) {
		$mockStack = $this->getMockBuilder(Stack::class)
			->addMethods(['getBoardId'])
			->getMock();

		$mockStack->method('getBoardId')->willReturn($boardId);
		return $mockStack;
	}
}
