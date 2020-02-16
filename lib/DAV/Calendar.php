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
use OCA\DAV\CalDAV\Plugin;
use OCA\DAV\DAV\Sharing\IShareable;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Service\CardService;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\PropPatch;

class Calendar extends ExternalCalendar implements IShareable {

	/** @var string */
	private $principalUri;

	/** @var string */
	private $calendarUri;

	/** @var string[] */
	private $children;
	/**
	 * @var \stdClass
	 */
	private $cardService;

	/**
	 * Calendar constructor.
	 *
	 * @param string $principalUri
	 * @param string $calendarUri
	 */
	public function __construct(string $principalUri, string $calendarUri, Board $board = null) {
		parent::__construct('deck', $calendarUri);

		$this->board = $board;

		$this->principalUri = $principalUri;
		$this->calendarUri = $calendarUri;


		if ($board) {
			/** @var CardService cardService */
			$cardService = \OC::$server->query(CardService::class);
			$this->children = $cardService->findCalendarEntries($board->getId());
		} else {
			$this->children = [];
		}
	}


	/**
	 * @inheritDoc
	 */
	function getOwner() {
		return $this->principalUri;
	}

	/**
	 * @inheritDoc
	 */
	function getACL() {
		return [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-read',
				'protected' => true,
			],
		];
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
	function calendarQuery(array $filters) {
		// In a real implementation this should actually filter
		return array_map(function (Card $card) {
			return $card->getId() . '.ics';
		}, $this->children);
	}

	/**
	 * @inheritDoc
	 */
	function createFile($name, $data = null) {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	function getChild($name) {
		if ($this->childExists($name)) {
			$card = array_values(array_filter(
				$this->children,
				function ($card) use (&$name) {
					return $card->getId() . '.ics' === $name;
				}
			));
			if (count($card) > 0) {
				return new CalendarObject($this, $name, $card[0]);
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	function getChildren() {
		$childNames = array_map(function (Card $card) {
			return $card->getId() . '.ics';
		}, $this->children);

		$children = [];

		foreach ($childNames as $name) {
			$children[] = $this->getChild($name);
		}

		return $children;
	}

	/**
	 * @inheritDoc
	 */
	function childExists($name) {
		return count(array_filter(
			$this->children,
			function ($card) use (&$name) {
				return $card->getId() . '.ics' === $name;
			}
		)) > 0;
	}

	/**
	 * @inheritDoc
	 */
	function delete() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	function getLastModified() {
		return $this->board->getLastModified();
	}

	/**
	 * @inheritDoc
	 */
	function getGroup() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	function propPatch(PropPatch $propPatch) {
		// We can just return here and let oc_properties handle everything
	}

	/**
	 * @inheritDoc
	 */
	function getProperties($properties) {
		// A backend should provide at least minimum properties
		return [
			'{DAV:}displayname' => 'Deck: ' . ($this->board ? $this->board->getTitle() : 'no board object provided'),
			'{http://apple.com/ns/ical/}calendar-color'  => '#' . $this->board->getColor(),
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO', 'VEVENT']),
		];
	}

	/**
	 * @inheritDoc
	 */
	function updateShares(array $add, array $remove) {
		// TODO: Implement updateShares() method.
	}

	/**
	 * @inheritDoc
	 */
	function getShares() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getResourceId() {
		// TODO: Implement getResourceId() method.
	}
}
