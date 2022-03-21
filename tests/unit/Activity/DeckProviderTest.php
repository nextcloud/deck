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

use OC\Activity\Event;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Card;
use OCP\Activity\IEvent;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\RichObjectStrings\IValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use OCA\Deck\Service\CardService;

class DeckProviderTest extends TestCase {

	/** @var DeckProvider */
	private $provider;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var ActivityManager|MockObject */
	private $activityManager;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var ICommentsManager|MockObject */
	private $commentsManager;

	/** @var CardService|MockObject */
	private $cardService;

	/** @var string */
	private $userId = 'admin';

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->config = $this->createMock(IConfig::class);
		$this->cardService = $this->createMock(CardService::class);
		$this->provider = new DeckProvider($this->urlGenerator, $this->activityManager, $this->userManager, $this->commentsManager, $this->l10nFactory, $this->config, $this->userId, $this->cardService);
	}

	private function mockEvent($objectType, $objectId, $objectName, $subject, $subjectParameters = []) {
		$data = [];
		$event = $this->createMock(IEvent::class);
		$event->expects($this->any())->method('getApp')->willReturn('deck');
		$event->expects($this->any())->method('getSubject')->willReturn($subject);
		$event->expects($this->any())->method('getSubjectParameters')->willReturn($subjectParameters);
		$event->expects($this->any())->method('getObjectType')->willReturn($objectType);
		$event->expects($this->any())->method('getObjectId')->willReturn($objectId);
		$event->expects($this->any())->method('getObjectName')->willReturn($objectName);
		$event->expects($this->any())->method('getAuthor')->willReturn('admin');
		$event->expects($this->any())->method('getMessage')->willReturn('');
		$event->expects($this->any())->method('setIcon')->will($this->returnCallback(function ($icon) use (&$data, $event) {
			$data['icon'] = $icon;
			return $event;
		}));
		$event->expects($this->any())->method('setParsedSubject')->will($this->returnCallback(function ($subject) use (&$data, $event) {
			$data['parsedSubject'] = $subject;
			return $event;
		}));
		$event->expects($this->any())->method('setRichSubject')->will($this->returnCallback(function ($subject) use (&$data, $event) {
			$data['richSubject'] = $subject;
			return $event;
		}));
		$event->expects($this->any())->method('getIcon')->will(
			$this->returnCallback(function () use (&$data) {
				return array_key_exists('icon', $data) ? $data['icon'] : 'noicon';
			})
		);
		return $event;
	}

	public function testParseFailureApp() {
		$this->expectException(\InvalidArgumentException::class);
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())->method('getApp')->willReturn('notdeck');
		$this->provider->parse('en_US', $event, $event);
	}

	public function dataEventIcons() {
		return [
			[ActivityManager::SUBJECT_LABEL_ASSIGN, 'core', 'actions/tag.svg'],
			[ActivityManager::SUBJECT_CARD_CREATE, 'files', 'add-color.svg'],
			[ActivityManager::SUBJECT_CARD_UPDATE, 'files', 'change.svg'],
			[ActivityManager::SUBJECT_CARD_DELETE, 'files', 'delete-color.svg'],
			[ActivityManager::SUBJECT_CARD_UPDATE_ARCHIVE, 'deck', 'archive.svg'],
			[ActivityManager::SUBJECT_CARD_RESTORE, 'core', 'actions/history.svg'],
			[ActivityManager::SUBJECT_ATTACHMENT_UPDATE, 'core', 'places/files.svg'],
		];
	}
	/**
	 * @dataProvider dataEventIcons
	 * @param $subject
	 * @param $icon
	 */
	public function testEventIcons($subject, $app, $icon) {
		$event = $this->mockEvent(
			ActivityManager::DECK_OBJECT_BOARD, 1, 'Board',
			$subject);
		$this->activityManager->expects($this->once())
			->method('getActivityFormat')
			->willReturn('test string {board}');
		$this->urlGenerator->expects($this->any())
			->method('imagePath')
			->will($this->returnCallback(function ($a, $i) {
				return $a . '/' . $i;
			}));
		$this->urlGenerator->expects($this->any())
			->method('getAbsoluteURL')
			->will($this->returnCallback(function ($url) {
				return $url;
			}));
		$this->provider->parse('en_US', $event);
		$this->assertEquals($app . '/' . $icon, $event->getIcon());
	}

	public function testDeckUrl() {
		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('deck.page.index')
			->willReturn('http://localhost/index.php/apps/deck/');
		$this->assertEquals(
			'http://localhost/index.php/apps/deck/#board/1/card/1',
			$this->provider->deckUrl('board/1/card/1')
		);
	}

	public function testParseObjectTypeBoard() {
		$this->urlGenerator->expects($this->any())
			->method('imagePath')
			->will($this->returnCallback(function ($a, $i) {
				return $a . '/' . $i;
			}));
		$this->activityManager->expects($this->once())
			->method('getActivityFormat')
			->willReturn('test string {board}');
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())->method('getDisplayName')->willReturn('Administrator');
		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($user);

		$richValidator = $this->createMock(IValidator::class);
		$event = new Event($richValidator);

		$event->setApp('deck');
		$event->setSubject(ActivityManager::SUBJECT_BOARD_CREATE);
		$event->setAffectedUser($this->userId);
		$event->setAuthor($this->userId);
		$event->setObject(ActivityManager::DECK_OBJECT_BOARD, 1, 'Board');

		$this->provider->parse('en_US', $event);
		$data = [
			'board' => [
				'type' => 'highlight',
				'id' => 1,
				'name' => 'Board',
				'link' => '#/board/1',
			],
			'user' => [
				'type' => 'user',
				'id' => 'admin',
				'name' => 'Administrator',
			]
		];
		$this->assertEquals($data, $event->getRichSubjectParameters());
	}

	public function testParseObjectTypeCard() {
		$this->urlGenerator->expects($this->any())
			->method('imagePath')
			->will($this->returnCallback(function ($a, $i) {
				return $a . '/' . $i;
			}));
		$this->activityManager->expects($this->once())
			->method('getActivityFormat')
			->willReturn('test string {card}');
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())->method('getDisplayName')->willReturn('Administrator');
		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($user);

		$richValidator = $this->createMock(IValidator::class);
		$event = new Event($richValidator);

		$event->setApp('deck');
		$event->setSubject(ActivityManager::SUBJECT_CARD_CREATE, ['card' => new Card()]);
		$event->setAffectedUser($this->userId);
		$event->setAuthor($this->userId);
		$event->setObject(ActivityManager::DECK_OBJECT_CARD, 1, 'Card');

		$this->provider->parse('en_US', $event);
		$data = [
			'card' => [
				'type' => 'highlight',
				'id' => 1,
				'name' => 'Card',
			],
			'user' => [
				'type' => 'user',
				'id' => 'admin',
				'name' => 'Administrator',
			]
		];
		$this->assertEquals($data, $event->getRichSubjectParameters());
		$this->assertEquals('test string Card', $event->getParsedSubject());
		$this->assertEquals('test string {card}', $event->getRichSubject());
		$this->assertEquals('', $event->getMessage());
	}

	public function testParseObjectTypeCardWithDiff() {
		$this->urlGenerator->expects($this->any())
			->method('imagePath')
			->will($this->returnCallback(function ($a, $i) {
				return $a . '/' . $i;
			}));
		$this->activityManager->expects($this->once())
			->method('getActivityFormat')
			->willReturn('test string {card}');
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())->method('getDisplayName')->willReturn('Administrator');
		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($user);

		$richValidator = $this->createMock(IValidator::class);
		$event = new Event($richValidator);

		$event->setApp('deck');
		$event->setSubject(ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION, [
			'before' => 'ABC',
			'after' => 'BCD',
			'diff' => true,
			'card' => new Card()
		]);
		$event->setAffectedUser($this->userId);
		$event->setAuthor($this->userId);
		$event->setObject(ActivityManager::DECK_OBJECT_CARD, 1, 'Card');
		$event->setMessage('BCD');

		$this->provider->parse('en_US', $event);
		$data = [
			'card' => [
				'type' => 'highlight',
				'id' => 1,
				'name' => 'Card',
			],
			'user' => [
				'type' => 'user',
				'id' => 'admin',
				'name' => 'Administrator',
			],
		];
		$this->assertEquals($data, $event->getRichSubjectParameters());
		$this->assertEquals('test string Card', $event->getParsedSubject());
		$this->assertEquals('test string {card}', $event->getRichSubject());
		$this->assertEquals('BCD', $event->getMessage());
		$this->assertEquals('BCD', $event->getParsedMessage());
	}

	public function testParseParamForBoard() {
		$params = [];
		$subjectParams = [
			'board' => [
				'id' => 1,
				'title' => 'Board name',
			],
		];
		$expected = [
			'board' => [
				'type' => 'highlight',
				'id' => 1,
				'name' => 'Board name',
				'link' => '#/board/1/',
			],
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForBoard', ['board', $subjectParams, $params]);
		$this->assertEquals($expected, $actual);
	}

	public function testParseParamForStack() {
		$params = [];
		$subjectParams = [
			'stack' => [
				'id' => 1,
				'title' => 'Stack name',
			],
		];
		$expected = [
			'stack' => [
				'type' => 'highlight',
				'id' => 1,
				'name' => 'Stack name',
			],
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForStack', ['stack', $subjectParams, $params]);
		$this->assertEquals($expected, $actual);
	}

	public function testParseParamForAttachment() {
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->willReturn('/link/to/attachment');
		$params = [];
		$subjectParams = [
			'attachment' => [
				'id' => 1,
				'data' => 'File name',
			],
			'card' => [
				'id' => 1,
			]
		];
		$expected = [
			'attachment' => [
				'type' => 'highlight',
				'id' => 1,
				'name' => 'File name',
				'link' => '/link/to/attachment',
			],
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForAttachment', ['attachment', $subjectParams, $params]);
		$this->assertEquals($expected, $actual);
	}

	public function testParseParamForAssignedUser() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn('User 1');
		$this->userManager->expects($this->once())
			->method('get')
			->willReturn($user);
		$params = [];
		$subjectParams = [
			'assigneduser' => 'user1',
		];
		$expected = [
			'assigneduser' => [
				'type' => 'user',
				'id' => 'user1',
				'name' => 'User 1'
			],
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForAssignedUser', [$subjectParams, $params]);
		$this->assertEquals($expected, $actual);
	}

	public function testParseParamForLabel() {
		$params = [];
		$subjectParams = [
			'label' => [
				'id' => 1,
				'title' => 'Label title',
			],
		];
		$expected = [
			'label' => [
				'type' => 'highlight',
				'id' => 1,
				'name' => 'Label title'
			],
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForLabel', [$subjectParams, $params]);
		$this->assertEquals($expected, $actual);
	}

	public function testParseParamForAclUser() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn('User 1');
		$this->userManager->expects($this->once())
			->method('get')
			->willReturn($user);
		$params = [];
		$subjectParams = [
			'acl' => [
				'id' => 1,
				'type' => Acl::PERMISSION_TYPE_USER,
				'participant' => 'user1'
			],
		];
		$expected = [
			'acl' => [
				'type' => 'user',
				'id' => 'user1',
				'name' => 'User 1'
			],
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForAcl', [$subjectParams, $params]);
		$this->assertEquals($expected, $actual);
	}

	public function testParseParamForAclGroup() {
		$params = [];
		$subjectParams = [
			'acl' => [
				'id' => 1,
				'type' => Acl::PERMISSION_TYPE_GROUP,
				'participant' => 'group'
			],
		];
		$expected = [
			'acl' => [
				'type' => 'highlight',
				'id' => 'group',
				'name' => 'group'
			],
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForAcl', [$subjectParams, $params]);
		$this->assertEquals($expected, $actual);
	}

	public function testParseParamForChanges() {
		$event = $this->createMock(IEvent::class);
		$params = [];
		$subjectParams = [
			'before' => 'ABC',
			'after' => 'BCD'
		];
		$expected = [
			'before' => [
				'type' => 'highlight',
				'id' => 'ABC',
				'name' => 'ABC'
			],
			'after' => [
				'type' => 'highlight',
				'id' => 'BCD',
				'name' => 'BCD'
			],
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForChanges', [$subjectParams, $params, $event]);
		$this->assertEquals($expected, $actual);
	}

	public function testParseParamForComment() {
		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())
			->method('getMessage')
			->willReturn('Comment content');
		$this->commentsManager->expects($this->once())
			->method('get')
			->with(123)
			->willReturn($comment);
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('setParsedMessage')
			->with('Comment content');
		$params = [];
		$subjectParams = [
			'comment' => 123
		];
		$expected = [
			'comment' => [
				'type' => 'highlight',
				'id' => 123,
				'name' => 'Comment content'
			]
		];
		$actual = $this->invokePrivate($this->provider, 'parseParamForComment', [$subjectParams, $params, $event]);
		$this->assertEquals($expected, $actual);
	}

	public function invokePrivate(&$object, $methodName, array $parameters = []) {
		$reflection = new \ReflectionClass(get_class($object));
		$method = $reflection->getMethod($methodName);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $parameters);
	}
}
