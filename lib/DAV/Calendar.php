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
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\PropPatch;

class Calendar extends ExternalCalendar {

	/** @var string */
	private $principalUri;
	/** @var string[] */
	private $children;
	/** @var DeckCalendarBackend */
	private $backend;
	/**  @var Board */
	private $board;

	public function __construct(string $principalUri, string $calendarUri, Board $board, DeckCalendarBackend $backend) {
		parent::__construct('deck', $calendarUri);

		$this->backend = $backend;
		$this->board = $board;

		$this->principalUri = $principalUri;

		if ($board) {
			$this->children = $this->backend->getChildren($board->getId());
		} else {
			$this->children = [];
		}
	}

	public function getOwner() {
		return $this->principalUri;
	}

	public function getACL() {
		$acl = [
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
		if ($this->backend->checkBoardPermission($this->board->getId(), Acl::PERMISSION_EDIT)) {
			$acl[] = [
				'privilege' => '{DAV:}write',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
			$acl[] = [
				'privilege' => '{DAV:}write-properties',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		return $acl;
	}

	public function setACL(array $acl) {
		throw new Forbidden('Setting ACL is not supported on this node');
	}

	public function getSupportedPrivilegeSet() {
		return null;
	}

	public function calendarQuery(array $filters) {
		// FIXME: In a real implementation this should actually filter
		return array_map(function ($card) {
			return $card->getCalendarPrefix() . '-' . $card->getId() . '.ics';
		}, $this->children);
	}

	public function createFile($name, $data = null) {
		throw new \Sabre\DAV\Exception\Forbidden('Creating a new entry is not implemented');
	}

	public function getChild($name) {
		if ($this->childExists($name)) {
			$card = array_values(array_filter(
				$this->children,
				function ($card) use (&$name) {
					return $card->getCalendarPrefix() . '-' . $card->getId() . '.ics' === $name;
				}
			));
			if (count($card) > 0) {
				return new CalendarObject($this, $name, $card[0], $this->backend);
			}
		}
	}

	public function getChildren() {
		$childNames = array_map(function ($card) {
			return $card->getCalendarPrefix() . '-' . $card->getId() . '.ics';
		}, $this->children);

		$children = [];

		foreach ($childNames as $name) {
			$children[] = $this->getChild($name);
		}

		return $children;
	}

	public function childExists($name) {
		return count(array_filter(
			$this->children,
			function ($card) use (&$name) {
				return $card->getCalendarPrefix() . '-' . $card->getId() . '.ics' === $name;
			}
		)) > 0;
	}


	public function delete() {
		return null;
	}

	public function getLastModified() {
		return $this->board->getLastModified();
	}

	public function getGroup() {
		return [];
	}

	public function propPatch(PropPatch $propPatch) {
		$properties = [
			'{DAV:}displayname',
			'{http://apple.com/ns/ical/}calendar-color'
		];
		$propPatch->handle($properties, function ($properties) {
			foreach ($properties as $key => $value) {
				switch ($key) {
					case '{DAV:}displayname':
						if (mb_substr($value, 0, strlen('Deck: '))) {
							$value = mb_substr($value, strlen('Deck: '));
						}
						$this->board->setTitle($value);
						break;
					case '{http://apple.com/ns/ical/}calendar-color':
						$this->board->setColor(substr($value, 1));
						break;
				}
			}
			return $this->backend->updateBoard($this->board);
		});
		// We can just return here and let oc_properties handle everything
	}

	/**
	 * @inheritDoc
	 */
	public function getProperties($properties) {
		return [
			'{DAV:}displayname' => 'Deck: ' . ($this->board ? $this->board->getTitle() : 'no board object provided'),
			'{http://apple.com/ns/ical/}calendar-color'  => '#' . $this->board->getColor(),
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO']),
		];
	}
}
