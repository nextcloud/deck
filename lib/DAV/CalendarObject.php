<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\DAV;

use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
use Sabre\CalDAV\ICalendarObject;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAVACL\IACL;
use Sabre\VObject\Component\VCalendar;

class CalendarObject implements ICalendarObject, IACL {

	/** @var Calendar */
	private $calendar;
	/** @var string */
	private $name;
	/** @var Card|Stack */
	private $sourceItem;
	/** @var DeckCalendarBackend */
	private $backend;
	/** @var VCalendar */
	private $calendarObject;

	public function __construct(Calendar $calendar, string $name, DeckCalendarBackend $backend, $sourceItem) {
		$this->calendar = $calendar;
		$this->name = $name;
		$this->sourceItem = $sourceItem;
		$this->backend = $backend;
		$this->calendarObject = $this->sourceItem->getCalendarObject();
	}

	public function getOwner() {
		return null;
	}

	public function getGroup() {
		return null;
	}

	public function getACL() {
		return $this->calendar->getACL();
	}

	public function setACL(array $acl) {
		throw new Forbidden('Setting ACL is not supported on this node');
	}

	public function getSupportedPrivilegeSet() {
		return null;
	}

	public function put($data) {
		throw new Forbidden('This calendar-object is read-only');
	}

	public function get() {
		if ($this->sourceItem) {
			return $this->calendarObject->serialize();
		}
	}

	public function getContentType() {
		return 'text/calendar; charset=utf-8';
	}

	public function getETag() {
		return '"' . md5($this->sourceItem->getLastModified()) . '"';
	}

	public function getSize() {
		return mb_strlen($this->calendarObject->serialize());
	}

	public function delete() {
		throw new Forbidden('This calendar-object is read-only');
	}

	public function getName() {
		return $this->name;
	}

	public function setName($name) {
		throw new Forbidden('This calendar-object is read-only');
	}

	public function getLastModified() {
		return $this->sourceItem->getLastModified();
	}
}
