<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\DAV;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
use OCA\Deck\StatusException;
use OCP\AppFramework\Db\DoesNotExistException;
use Sabre\CalDAV\ICalendarObject;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAVACL\IACL;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\InvalidDataException;

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
		$acl = $this->calendar->getACL();
		if ($this->sourceItem instanceof Stack) {
			return array_values(array_filter($acl, static function (array $entry): bool {
				return $entry['privilege'] !== '{DAV:}write-content';
			}));
		}

		return $acl;
	}

	public function setACL(array $acl) {
		throw new Forbidden('Setting ACL is not supported on this node');
	}

	public function getSupportedPrivilegeSet() {
		return null;
	}

	public function put($data) {
		if (!($this->sourceItem instanceof Card)) {
			throw new Forbidden('This calendar-object is read-only');
		}

		// MultipleObjectsReturnedException is intentionally not caught:
		// a primary-key lookup returning multiple rows is a data integrity bug
		// and should surface as a 500 in the log.
		try {
			$this->sourceItem = $this->backend->updateCardFromCalendarObject($this->sourceItem, $this->readPutData($data));
		} catch (DoesNotExistException $e) {
			throw new NotFound($e->getMessage(), 0, $e);
		} catch (InvalidDataException $e) {
			throw new BadRequest($e->getMessage(), 0, $e);
		} catch (BadRequestException $e) {
			throw new BadRequest($e->getMessage(), 0, $e);
		} catch (StatusException $e) {
			throw new Forbidden($e->getMessage(), 0, $e);
		}
		$this->calendarObject = $this->sourceItem->getCalendarObject();
	}

	private function readPutData($data): string {
		if (is_resource($data)) {
			$content = stream_get_contents($data);
			if ($content === false) {
				throw new BadRequest('Could not read calendar-object data');
			}
			return $content;
		}

		return (string)$data;
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
		throw new Forbidden('Deleting tasks via CalDAV is not supported');
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
