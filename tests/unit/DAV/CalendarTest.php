<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\DAV;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
use Test\TestCase;

class CalendarTest extends TestCase {

	public function testGetChildrenUsesStoredDavUriForCards(): void {
		$board = new Board();
		$board->setId(12);
		$board->setTitle('Test Board');
		$board->setColor('0082c9');

		$card = new Card();
		$card->setId(321);
		$card->setTitle('Client task');
		$card->setDescription('');
		$card->setStackId(9);
		$card->setDavUri('client-task.ics');

		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->expects($this->once())
			->method('getChildren')
			->with(12)
			->willReturn([$card]);

		$calendar = new Calendar('principals/users/admin', 'board-12', $board, $backend);
		$children = $calendar->getChildren();

		$this->assertCount(1, $children);
		$this->assertSame('client-task.ics', $children[0]->getName());
	}

	public function testGetChildResolvesStoredDavUriForCards(): void {
		$board = new Board();
		$board->setId(12);
		$board->setTitle('Test Board');
		$board->setColor('0082c9');

		$card = new Card();
		$card->setId(321);
		$card->setTitle('Client task');
		$card->setDescription('');
		$card->setStackId(9);
		$card->setDavUri('client-task.ics');

		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->expects($this->once())
			->method('getChildren')
			->with(12)
			->willReturn([$card]);

		$calendar = new Calendar('principals/users/admin', 'board-12', $board, $backend);
		$child = $calendar->getChild('client-task.ics');

		$this->assertSame('client-task.ics', $child->getName());
	}

	public function testNormalizeRequestedDisplayNameKeepsBoardCalendarTitle(): void {
		$board = new Board();
		$board->setId(12);
		$board->setTitle('Test Board');
		$board->setColor('0082c9');

		$backend = $this->createMock(DeckCalendarBackend::class);
		$calendar = new Calendar('principals/users/admin', 'board-12', $board, $backend);

		$method = new \ReflectionMethod($calendar, 'normalizeRequestedDisplayName');
		$method->setAccessible(true);

		$this->assertSame('Renamed Board', $method->invoke($calendar, 'Deck: Renamed Board'));
	}

	public function testNormalizeRequestedDisplayNameStripsBoardPrefixForStackCalendars(): void {
		$board = new Board();
		$board->setId(12);
		$board->setTitle('Test Board');
		$board->setColor('0082c9');

		$stack = new Stack();
		$stack->setId(9);
		$stack->setBoardId(12);
		$stack->setTitle('Liste 1');

		$backend = $this->createMock(DeckCalendarBackend::class);
		$calendar = new Calendar('principals/users/admin', 'stack-9', $board, $backend, $stack);

		$method = new \ReflectionMethod($calendar, 'normalizeRequestedDisplayName');
		$method->setAccessible(true);

		$this->assertSame('Renamed List', $method->invoke($calendar, 'Deck: Test Board / Renamed List'));
	}
}
