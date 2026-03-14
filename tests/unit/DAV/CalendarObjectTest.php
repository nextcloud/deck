<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\DAV;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
use Sabre\VObject\Component\VCalendar;
use Test\TestCase;

class CalendarObjectTest extends TestCase {

	public function testStackObjectAclRemovesWriteForNonManagers(): void {
		$calendar = $this->createMock(Calendar::class);
		$calendar->method('getACL')->willReturn([
			['privilege' => '{DAV:}read', 'principal' => 'principals/users/jacob', 'protected' => true],
			['privilege' => '{DAV:}write', 'principal' => 'principals/users/jacob', 'protected' => true],
			['privilege' => '{DAV:}write-properties', 'principal' => 'principals/users/jacob', 'protected' => true],
		]);
		$calendar->method('getBoardId')->willReturn(12);

		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->expects($this->once())
			->method('checkBoardPermission')
			->with(12, Acl::PERMISSION_MANAGE)
			->willReturn(false);

		$stack = $this->createMock(Stack::class);
		$stack->method('getCalendarObject')->willReturn(new VCalendar());

		$calendarObject = new CalendarObject($calendar, 'stack-9.ics', $backend, $stack);
		$acl = $calendarObject->getACL();

		$this->assertSame(['{DAV:}read', '{DAV:}write-properties'], array_column($acl, 'privilege'));
	}

	public function testCardObjectAclKeepsWriteForEditors(): void {
		$expectedAcl = [
			['privilege' => '{DAV:}read', 'principal' => 'principals/users/jacob', 'protected' => true],
			['privilege' => '{DAV:}write', 'principal' => 'principals/users/jacob', 'protected' => true],
			['privilege' => '{DAV:}write-properties', 'principal' => 'principals/users/jacob', 'protected' => true],
		];

		$calendar = $this->createMock(Calendar::class);
		$calendar->method('getACL')->willReturn($expectedAcl);

		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->expects($this->never())->method('checkBoardPermission');

		$card = $this->createMock(Card::class);
		$card->method('getCalendarObject')->willReturn(new VCalendar());

		$calendarObject = new CalendarObject($calendar, 'card-7.ics', $backend, $card);

		$this->assertSame($expectedAcl, $calendarObject->getACL());
	}

	public function testGetETagPassesCalendarContextToBackendFingerprint(): void {
		$calendar = $this->createMock(Calendar::class);
		$calendar->method('getBoardId')->willReturn(12);
		$calendar->method('getStackId')->willReturn(9);

		$backend = $this->createMock(DeckCalendarBackend::class);

		$card = $this->createMock(Card::class);
		$card->method('getCalendarObject')->willReturn(new VCalendar());
		$card->method('getLastModified')->willReturn(1234567890);

		$backend->expects($this->once())
			->method('getObjectRevisionFingerprint')
			->with($card, 12, 9)
			->willReturn('fingerprint');

		$calendarObject = new CalendarObject($calendar, 'card-7.ics', $backend, $card);

		$this->assertSame('"' . md5('1234567890|fingerprint') . '"', $calendarObject->getETag());
	}
}
