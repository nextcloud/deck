<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Deck\Db;

use Sabre\VObject\Component\VCalendar;

/**
 * @method int getId()
 * @method int getBoardId()
 * @method int getDeletedAt()
 * @method int getLastModified()
 * @method int getOrder()
 */
class Stack extends RelationalEntity {
	protected $title;
	protected $boardId;
	protected $deletedAt = 0;
	protected $lastModified = 0;
	protected $cards = [];
	protected $order;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('deletedAt', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('order', 'integer');
	}

	public function setCards($cards) {
		$this->cards = $cards;
	}

	public function jsonSerialize(): array {
		$json = parent::jsonSerialize();
		if (empty($this->cards)) {
			unset($json['cards']);
		}
		return $json;
	}

	public function getCalendarObject(): VCalendar {
		$calendar = new VCalendar();
		$event = $calendar->createComponent('VTODO');
		$event->UID = 'deck-stack-' . $this->getId();
		$event->SUMMARY = 'List : ' . $this->getTitle();
		$calendar->add($event);
		return $calendar;
	}

	public function getCalendarPrefix(): string {
		return 'stack';
	}

	public function getETag() {
		return md5((string)$this->getLastModified());
	}
}
