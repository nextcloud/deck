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

use OCA\Deck\Db\Card;
use OCA\Deck\Service\CardService;
use Sabre\VObject\Component\VCalendar;

class CalendarObject implements \Sabre\CalDAV\ICalendarObject, \Sabre\DAVACL\IACL {

	/** @var Calendar */
	private $calendar;

	/** @var string */
	private $name;
	/**
	 * @var Card
	 */
	private $sourceItem;

	/**
	 * CalendarObject constructor.
	 *
	 * @param Calendar $calendar
	 * @param string $name
	 */
	public function __construct(Calendar $calendar, string $name, $sourceItem = null) {
		$this->calendar = $calendar;
		$this->name = $name;
		$this->sourceItem = $sourceItem;
	}

	/**
	 * @inheritDoc
	 */
	function getOwner() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	function getGroup() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	function getACL() {
		return $this->calendar->getACL();
	}

	/**
	 * @inheritDoc
	 */
	function setACL(array $acl) {
		throw new \Sabre\DAV\Exception\Forbidden('Setting ACL is not supported on this node');
	}

	/**
	 * @inheritDoc
	 */
	function getSupportedPrivilegeSet() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	function put($data) {
		throw new \Sabre\DAV\Exception\Forbidden('This calendar-object is read-only');
	}

	/**
	 * @inheritDoc
	 */
	function get() {
		if ($this->sourceItem) {
			return $this->sourceItem->getCalendarObject()->serialize();
		}
	}

	/**
	 * @inheritDoc
	 */
	function getContentType() {
		return 'text/calendar; charset=utf-8';
	}

	/**
	 * @inheritDoc
	 */
	function getETag() {
		return '"' . md5($this->get()) . '"';
	}

	/**
	 * @inheritDoc
	 */
	function getSize() {
		return strlen($this->get());
	}

	/**
	 * @inheritDoc
	 */
	function delete() {
		throw new \Sabre\DAV\Exception\Forbidden('This calendar-object is read-only');
	}

	/**
	 * @inheritDoc
	 */
	function getName() {
		return $this->name;
	}

	/**
	 * @inheritDoc
	 */
	function setName($name) {
		throw new \Sabre\DAV\Exception\Forbidden('This calendar-object is read-only');
	}

	/**
	 * @inheritDoc
	 */
	function getLastModified() {
		return $this->sourceItem->getLastModified();
	}
}
