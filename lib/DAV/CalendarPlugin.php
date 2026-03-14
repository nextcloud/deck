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

		$boards = array_values($this->getEnabledBoardsById());

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

		return $this->resolveCalendar($principalUri, $calendarUri) !== null;
	}

	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar {
		if (!$this->calendarIntegrationEnabled) {
			return null;
		}

		return $this->resolveCalendar($principalUri, $calendarUri);
	}

	/**
	 * @return array<int, Board>
	 */
	private function getEnabledBoardsById(): array {
		$boards = [];
		foreach ($this->backend->getBoards() as $board) {
			if ($this->configService->isCalendarEnabled($board->getId())) {
				$boards[$board->getId()] = $board;
			}
		}

		return $boards;
	}

	private function resolveCalendar(string $principalUri, string $calendarUri): ?ExternalCalendar {
		$normalizedCalendarUri = $this->normalizeCalendarUri($calendarUri);
		$enabledBoardsById = $this->getEnabledBoardsById();
		$perListMode = $this->configService->getCalDavListMode() === ConfigService::SETTING_CALDAV_LIST_MODE_PER_LIST_CALENDAR;

		try {
			if ($perListMode && str_starts_with($normalizedCalendarUri, 'stack-')) {
				$stack = $this->backend->getStack((int)str_replace('stack-', '', $normalizedCalendarUri));
				$board = $enabledBoardsById[$stack->getBoardId()] ?? null;
				if ($board === null) {
					return null;
				}
				return new Calendar($principalUri, $normalizedCalendarUri, $board, $this->backend, $stack);
			}

			if (!$perListMode && str_starts_with($normalizedCalendarUri, 'board-')) {
				$boardId = (int)str_replace('board-', '', $normalizedCalendarUri);
				$board = $enabledBoardsById[$boardId] ?? null;
				if ($board === null) {
					return null;
				}
				return new Calendar($principalUri, $normalizedCalendarUri, $board, $this->backend);
			}
		} catch (NotFound $e) {
			// We can just return null if we have no matching board/stack
		}

		return null;
	}

	private function normalizeCalendarUri(string $calendarUri): string {
		$prefix = 'app-generated--deck--';
		if (str_starts_with($calendarUri, $prefix)) {
			return substr($calendarUri, strlen($prefix));
		}

		return $calendarUri;
	}
}
