<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\DAV;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Stack;
use OCA\Deck\Service\ConfigService;
use Test\TestCase;

class CalendarPluginTest extends TestCase {

	public function testHasCalendarInCalendarHomeNormalizesAppGeneratedBoardUri(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$configService = $this->createMock(ConfigService::class);

		$configService->method('get')->with('calendar')->willReturn(true);
		$configService->method('getCalDavListMode')->willReturn(ConfigService::SETTING_CALDAV_LIST_MODE_ROOT_TASKS);

		$board = new Board();
		$board->setId(2);

		$backend->expects($this->once())
			->method('getBoards')
			->willReturn([$board]);

		$plugin = new CalendarPlugin($backend, $configService);

		$this->assertTrue(
			$plugin->hasCalendarInCalendarHome('principals/users/admin', 'app-generated--deck--board-2')
		);
	}

	public function testGetCalendarInCalendarHomeNormalizesAppGeneratedStackUri(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$configService = $this->createMock(ConfigService::class);

		$configService->method('get')->with('calendar')->willReturn(true);

		$stack = new Stack();
		$stack->setId(5);
		$stack->setBoardId(2);

		$board = new Board();
		$board->setId(2);
		$board->setTitle('Test Board');
		$board->setColor('0082c9');

		$backend->expects($this->once())
			->method('getStack')
			->with(5)
			->willReturn($stack);
		$backend->expects($this->once())
			->method('getBoard')
			->with(2)
			->willReturn($board);

		$plugin = new CalendarPlugin($backend, $configService);

		$calendar = $plugin->getCalendarInCalendarHome('principals/users/admin', 'app-generated--deck--stack-5');

		$this->assertInstanceOf(Calendar::class, $calendar);
	}
}
