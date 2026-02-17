<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\DAV;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider;
use OCA\Deck\Db\Board;
use OCA\Deck\Service\ConfigService;
use Sabre\DAV\Exception\NotFound;

class CalendarPlugin implements ICalendarProvider {

	/** @var DeckCalendarBackend */
	private $backend;
	/** @var ConfigService */
	private $configService;
	/** @var bool */
	private $calendarIntegrationEnabled;

	public function __construct(DeckCalendarBackend $backend, ConfigService $configService) {
		$this->backend = $backend;
		$this->configService = $configService;
		$this->calendarIntegrationEnabled = $configService->get('calendar');
	}

	public function getAppId(): string {
		return 'deck';
	}

	public function fetchAllForCalendarHome(string $principalUri): array {
		if (!$this->calendarIntegrationEnabled) {
			return [];
		}

		$configService = $this->configService;
		$boards = array_values(array_filter($this->backend->getBoards(), function ($board) use ($configService) {
			return $configService->isCalendarEnabled($board->getId());
		}));

		if ($this->configService->getCalDavListMode() === ConfigService::SETTING_CALDAV_LIST_MODE_PER_LIST_CALENDAR) {
			$calendars = [];
			foreach ($boards as $board) {
				foreach ($this->backend->getStacks($board->getId()) as $stack) {
					$calendars[] = new Calendar($principalUri, 'stack-' . $stack->getId(), $board, $this->backend, $stack);
				}
			}
			return $calendars;
		}

		return array_map(function (Board $board) use ($principalUri) {
			return new Calendar($principalUri, 'board-' . $board->getId(), $board, $this->backend);
		}, $boards);
	}

	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool {
		if (!$this->calendarIntegrationEnabled) {
			return false;
		}

		if ($this->configService->getCalDavListMode() === ConfigService::SETTING_CALDAV_LIST_MODE_PER_LIST_CALENDAR) {
			foreach ($this->backend->getBoards() as $board) {
				foreach ($this->backend->getStacks($board->getId()) as $stack) {
					if ($calendarUri === 'stack-' . $stack->getId()) {
						return true;
					}
				}
			}
			return false;
		}

		$boards = array_map(static fn (Board $board): string => 'board-' . $board->getId(), $this->backend->getBoards());
		return in_array($calendarUri, $boards, true);
	}

	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar {
		if (!$this->calendarIntegrationEnabled) {
			return null;
		}

		if ($this->hasCalendarInCalendarHome($principalUri, $calendarUri)) {
			try {
				if (str_starts_with($calendarUri, 'stack-')) {
					$stack = $this->backend->getStack((int)str_replace('stack-', '', $calendarUri));
					$board = $this->backend->getBoard($stack->getBoardId());
					return new Calendar($principalUri, $calendarUri, $board, $this->backend, $stack);
				}

				$board = $this->backend->getBoard((int)str_replace('board-', '', $calendarUri));
				return new Calendar($principalUri, $calendarUri, $board, $this->backend);
			} catch (NotFound $e) {
				// We can just return null if we have no matching board
			}
		}
		return null;
	}
}
