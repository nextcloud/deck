<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use Sabre\VObject\Component\VCalendar;

/**
 * @method int getId()
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getBoardId()
 * @method void setBoardId(int $boardId)
 * @method int getDeletedAt()
 * @method void setDeletedAt(int $deletedAt)
 * @method int getLastModified()
 * @method void setLastModified(int $lastModified)
 * @method \int getOrder()
 * @method void setOrder(int $order)
 * @method Card[] getCards()
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
