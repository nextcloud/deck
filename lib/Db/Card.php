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

use DateTime;
use DateTimeZone;
use Sabre\VObject\Component\VCalendar;

class Card extends RelationalEntity {
	public const TITLE_MAX_LENGTH = 255;

	protected $title;
	protected $description;
	protected $descriptionPrev;
	protected $stackId;
	protected $type;
	protected $lastModified;
	protected $lastEditor;
	protected $createdAt;
	protected $labels;
	protected $assignedUsers;
	protected $attachments;
	protected $attachmentCount;
	protected $owner;
	protected $order;
	protected $archived = false;
	protected $duedate;
	protected $notified = false;
	protected $deletedAt = 0;
	protected $commentsUnread = 0;

	private $databaseType = 'sqlite';

	public const DUEDATE_FUTURE = 0;
	public const DUEDATE_NEXT = 1;
	public const DUEDATE_NOW = 2;
	public const DUEDATE_OVERDUE = 3;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('stackId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('archived', 'boolean');
		$this->addType('notified', 'boolean');
		$this->addType('deletedAt', 'integer');
		$this->addRelation('labels');
		$this->addRelation('assignedUsers');
		$this->addRelation('attachments');
		$this->addRelation('attachmentCount');
		$this->addRelation('participants');
		$this->addRelation('commentsUnread');
		$this->addResolvable('owner');
	}

	public function setDatabaseType($type) {
		$this->databaseType = $type;
	}

	public function getDuedate($isoFormat = false) {
		if ($this->duedate === null) {
			return null;
		}
		$dt = new DateTime($this->duedate);
		if (!$isoFormat && $this->databaseType === 'mysql') {
			return $dt->format('Y-m-d H:i:s');
		}
		return $dt->format('c');
	}

	public function jsonSerialize() {
		$json = parent::jsonSerialize();
		$json['overdue'] = self::DUEDATE_FUTURE;
		$due = strtotime($this->duedate);

		$today = new DateTime();
		$today->setTime(0, 0);

		$match_date = new DateTime($this->duedate);

		$match_date->setTime(0, 0);

		$diff = $today->diff($match_date);
		$diffDays = (integer) $diff->format('%R%a'); // Extract days count in interval

		if ($due !== false) {
			if ($diffDays === 1) {
				$json['overdue'] = self::DUEDATE_NEXT;
			}
			if ($diffDays === 0) {
				$json['overdue'] = self::DUEDATE_NOW;
			}
			if ($diffDays < 0) {
				$json['overdue'] = self::DUEDATE_OVERDUE;
			}
		}
		$json['duedate'] = $this->getDuedate(true);
		unset($json['notified']);
		unset($json['descriptionPrev']);
		return $json;
	}

	public function getCalendarObject(): VCalendar {
		$calendar = new VCalendar();
		$event = $calendar->createComponent('VTODO');
		$event->UID = 'deck-card-' . $this->getId();
		if ($this->getDuedate()) {
			$creationDate = new DateTime();
			$creationDate->setTimestamp($this->createdAt);
			$event->DTSTAMP = $creationDate;
			$event->DUE = new DateTime($this->getDuedate(true), new DateTimeZone('UTC'));
		}
		$event->add('RELATED-TO', 'deck-stack-' . $this->getStackId());

		// FIXME: For write support: CANCELLED / IN-PROCESS handling
		$event->STATUS = $this->getArchived() ? "COMPLETED" : "NEEDS-ACTION";
		if ($this->getArchived()) {
			$date = new DateTime();
			$date->setTimestamp($this->getLastModified());
			$event->COMPLETED = $date;
			//$event->add('PERCENT-COMPLETE', 100);
		}
		if (count($this->getLabels()) > 0) {
			$event->CATEGORIES = array_map(function ($label) {
				return $label->getTitle();
			}, $this->getLabels());
		}

		$event->SUMMARY = $this->getTitle();
		$event->DESCRIPTION = $this->getDescription();
		$calendar->add($event);
		return $calendar;
	}

	public function getCalendarPrefix(): string {
		return 'card';
	}

	public function getETag() {
		return md5((string)$this->getLastModified());
	}
}
