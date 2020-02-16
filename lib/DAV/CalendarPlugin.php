<?php
/**
 * @copyright 2020, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\Deck\DAV;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider;
use OCA\Deck\Db\Board;
use OCA\Deck\Service\BoardService;

class CalendarPlugin implements ICalendarProvider {

	/**
	 * @var BoardService
	 */
	private $boardService;

	public function __construct(BoardService $boardService) {
		$this->boardService = $boardService;
	}

	/**
	 * @inheritDoc
	 */
	public function getAppId(): string {
		return 'deck';
	}

	/**
	 * @inheritDoc
	 */
	public function fetchAllForCalendarHome(string $principalUri): array {
		$boards = $this->boardService->findAll();
		return array_map(function (Board $board) use ($principalUri) {
			return new Calendar($principalUri, 'board-' . $board->getId(), $board);
		}, $boards);
	}

	/**
	 * @inheritDoc
	 */
	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool {
		$boards = array_map(function(Board $board) {
			return 'board-' . $board->getId();
		}, $this->boardService->findAll());
		return in_array($calendarUri, $boards, true);
	}

	/**
	 * @inheritDoc
	 */
	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar {
		if ($this->hasCalendarInCalendarHome($principalUri, $calendarUri)) {
			$board = $this->boardService->find(str_replace('board-', '', $calendarUri));
			return new Calendar($principalUri, $calendarUri, $board);
		}

		return null;
	}
}
