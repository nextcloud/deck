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
use Sabre\DAV\Exception\NotFound;

class CalendarPlugin implements ICalendarProvider {

	/** @var DeckCalendarBackend */
	private $backend;

	public function __construct(DeckCalendarBackend $backend) {
		$this->backend = $backend;
	}

	public function getAppId(): string {
		return 'deck';
	}

	public function fetchAllForCalendarHome(string $principalUri): array {
		return array_map(function (Board $board) use ($principalUri) {
			return new Calendar($principalUri, 'board-' . $board->getId(), $board, $this->backend);
		}, $this->backend->getBoards());
	}

	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool {
		$boards = array_map(static function (Board $board) {
			return 'board-' . $board->getId();
		}, $this->backend->getBoards());
		return in_array($calendarUri, $boards, true);
	}

	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar {
		if ($this->hasCalendarInCalendarHome($principalUri, $calendarUri)) {
			try {
				$board = $this->backend->getBoard((int)str_replace('board-', '', $calendarUri));
				return new Calendar($principalUri, $calendarUri, $board, $this->backend);
			} catch (NotFound $e) {
				// We can just return null if we have no matching board
			}
		}
		return null;
	}
}
