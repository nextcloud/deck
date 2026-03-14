<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\DAV;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
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
}
