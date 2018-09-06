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

use OCP\Activity\IEvent;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class DeckProviderTest extends TestCase {

	/** @var DeckProvider */
	private $provider;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var ActivityManager|MockObject */
	private $activityManager;

	/** @var string */
	private $userId = 'admin';

	public function setUp() {
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->provider = new DeckProvider($this->urlGenerator, $this->activityManager, $this->userId);
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
		$event->expects($this->any())->method('setIcon')->will($this->returnCallback(function($icon) use (&$data) {
			$data['icon'] = $icon;
		}));
		$event->expects($this->any())->method('getIcon')->will(
			$this->returnCallback(function() use (&$data) {
				return array_key_exists('icon', $data) ? $data['icon'] : 'noicon';
			})
		);
		return $event;
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testParseFailureApp() {
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())->method('getApp')->willReturn('notdeck');
		$this->provider->parse('en_US', $event, $event);
	}

	public function dataEventIcons() {
		return [
			[ActivityManager::SUBJECT_CARD_MOVE_STACK, 'deck', 'deck-dark.svg'],
			[ActivityManager::SUBJECT_CARD_UPDATE, 'files', 'change.svg'],
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
		$this->urlGenerator->expects($this->any())
			->method('imagePath')
			->will($this->returnCallback(function($a, $i) {
				return $a . '/' . $i;
			}));
		$this->provider->parse('en_US', $event);
		$this->assertEquals($app . '/' . $icon, $event->getIcon());
	}

	public function testDeckUrl() {
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('deck.page.index')
			->willReturn('http://localhost/index.php/apps/deck/');
		$this->assertEquals(
			'http://localhost/index.php/apps/deck/#!board/1/card/1',
			$this->provider->deckUrl('board/1/card/1')
		);
	}

}
