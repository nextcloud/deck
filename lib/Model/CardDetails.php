<?php
/**
 * @copyright Copyright (c) 2022 Raul Ferreira Fuentes <raul@nextcloud.com>
 *
 * @author Raul Ferreira Fuentes <raul@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Deck\Model;

use DateTime;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;

class CardDetails extends Card
{
	private Card $card;
	private ?Board $board;

	public function __construct(
		Card $card,
		Board $board = null
	) {
		parent::__construct();
		$this->card = $card;
		$this->board = $board;
	}

	protected function getter($name) {
		return $this->card->getter($name);
	}

	public function jsonSerialize(): array {
		$array = parent::jsonSerialize();
		$array['boardId'] = $this->board->id ?? null;
		$array['overdue'] = $this->getDueStatus();
		$array['foo'] = 'bar';

		unset($array['notified']);
		unset($array['descriptionPrev']);
		unset($array['relatedStack']);
		unset($array['relatedBoard']);

		return $array;
	}

	private function getDueStatus(): int {
		$today = new DateTime();
		$today->setTime(0, 0);

		$match_date = new DateTime($this->duedate);
		$match_date->setTime(0, 0);

		$diff = $today->diff($match_date);
		$diffDays = (integer) $diff->format('%R%a'); // Extract days count in interval

		if ($diffDays === 1) {
			return self::DUEDATE_NEXT;
		}
		if ($diffDays === 0) {
			return self::DUEDATE_NOW;
		}
		if ($diffDays < 0) {
			return self::DUEDATE_OVERDUE;
		}

		return self::DUEDATE_FUTURE;
	}
}
