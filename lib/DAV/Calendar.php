<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\DAV;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Plugin;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
use Sabre\CalDAV\CalendarQueryValidator;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Reader;

class Calendar extends ExternalCalendar {

	/** @var string */
	private $principalUri;
	/** @var string[] */
	private $children;
	/** @var DeckCalendarBackend */
	private $backend;
	/** @var Board */
	private $board;
	/** @var Stack|null */
	private $stack;

	public function __construct(string $principalUri, string $calendarUri, Board $board, DeckCalendarBackend $backend, ?Stack $stack = null) {
		parent::__construct('deck', $calendarUri);

		$this->backend = $backend;
		$this->board = $board;
		$this->stack = $stack;

		$this->principalUri = $principalUri;
	}

	public function getOwner() {
		return $this->principalUri;
	}

	public function isShared(): bool {
		return false;
	}

	public function getACL() {
		// Always allow read. Only expose write capabilities when the current
		// principal can edit/manage the underlying board.
		$acl = [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
		];
		if ($this->backend->checkBoardPermission($this->board->getId(), Acl::PERMISSION_EDIT)) {
			$acl[] = [
				'privilege' => '{DAV:}write',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		// write-properties is needed to allow users with manage permission to
		// toggle calendar visibility and update board-level metadata.
		if ($this->backend->checkBoardPermission($this->board->getId(), Acl::PERMISSION_MANAGE)) {
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
		$result = [];
		$objects = $this->getChildren();

		foreach ($objects as $object) {
			if ($this->validateFilterForObject($object, $filters)) {
				$result[] = $object->getName();
			}
		}

		return $result;
	}

	protected function validateFilterForObject($object, array $filters) {
		$vObject = Reader::read($object->get());

		$validator = new CalendarQueryValidator();
		$result = $validator->validate($vObject, $filters);

		// Destroy circular references so PHP will GC the object.
		$vObject->destroy();

		return $result;
	}

	public function createFile($name, $data = null) {
		$normalizedName = $this->normalizeCalendarObjectName($name);
		if ($this->childExists($normalizedName)) {
			$this->getChild($normalizedName)->put((string)$data);
			$this->children = [];
			return;
		}

		$owner = $this->extractUserIdFromPrincipalUri();
		$this->backend->createCalendarObject(
			$this->board->getId(),
			$owner,
			(string)$data,
			$this->extractCardIdFromNormalizedName($normalizedName),
			$this->stack?->getId()
		);
		$this->children = [];
	}

	public function getChild($name) {
		$name = $this->normalizeCalendarObjectName($name);
		foreach ($this->getBackendChildren() as $card) {
			$canonicalName = $card->getCalendarPrefix() . '-' . $card->getId() . '.ics';
			if ($this->isMatchingCalendarObjectName($name, $canonicalName)) {
				return new CalendarObject($this, $canonicalName, $this->backend, $card);
			}
		}

		$fallbackItem = $this->backend->findCalendarObjectByName(
			$name,
			$this->board->getId(),
			$this->stack?->getId()
		);
		if ($fallbackItem !== null) {
			$canonicalName = $fallbackItem->getCalendarPrefix() . '-' . $fallbackItem->getId() . '.ics';
			return new CalendarObject($this, $canonicalName, $this->backend, $fallbackItem);
		}

		if ($this->shouldUsePlaceholderForMissingObject()) {
			$placeholderItem = $this->buildPlaceholderCalendarObject($name);
			if ($placeholderItem !== null) {
				$canonicalName = $placeholderItem->getCalendarPrefix() . '-' . $placeholderItem->getId() . '.ics';
				return new CalendarObject($this, $canonicalName, $this->backend, $placeholderItem);
			}
		}

		throw new NotFound('Node not found');
	}

	public function getChildren() {
		$children = [];
		foreach ($this->getBackendChildren() as $card) {
			$name = $card->getCalendarPrefix() . '-' . $card->getId() . '.ics';
			$children[] = new CalendarObject($this, $name, $this->backend, $card);
		}

		return $children;
	}

	private function getBackendChildren() {
		if ($this->children) {
			return $this->children;
		}

		if ($this->board) {
			if ($this->stack !== null) {
				$this->children = $this->backend->getChildrenForStack($this->stack->getId());
			} else {
				$this->children = $this->backend->getChildren($this->board->getId());
			}
		} else {
			$this->children = [];
		}

		return $this->children;
	}

	public function childExists($name) {
		$name = $this->normalizeCalendarObjectName($name);
		return count(array_filter(
			$this->getBackendChildren(),
			function ($card) use (&$name) {
				$canonicalName = $card->getCalendarPrefix() . '-' . $card->getId() . '.ics';
				return $this->isMatchingCalendarObjectName($name, $canonicalName);
			}
		)) > 0;
	}


	public function delete() {
		throw new Forbidden('Deleting an entry is not implemented');
	}

	public function getLastModified() {
		// Keep collection last-modified monotonic and avoid hash offsets that
		// can move backwards for different fingerprints.
		return $this->board->getLastModified();
	}

	public function getGroup() {
		return [];
	}

	public function getBoardId(): int {
		return $this->board->getId();
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
						if (!$this->backend->checkBoardPermission($this->board->getId(), Acl::PERMISSION_MANAGE)) {
							throw new Forbidden('no permission to change the displayname');
						}
						if (mb_strpos($value, 'Deck: ') === 0) {
							$value = mb_substr($value, mb_strlen('Deck: '));
						}
						$this->board->setTitle($value);
						break;
					case '{http://apple.com/ns/ical/}calendar-color':
						if (!$this->backend->checkBoardPermission($this->board->getId(), Acl::PERMISSION_MANAGE)) {
							throw new Forbidden('no permission to change the calendar color');
						}
						$color = substr($value, 1, 6);
						if (!preg_match('/[a-f0-9]{6}/i', $color)) {
							throw new InvalidDataException('No valid color provided');
						}
						$this->board->setColor($color);
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
		$displayName = 'Deck: ' . ($this->board ? $this->board->getTitle() : 'no board object provided');
		if ($this->stack !== null) {
			$displayName .= ' / ' . $this->stack->getTitle();
		}

		return [
			'{DAV:}displayname' => $displayName,
			'{http://apple.com/ns/ical/}calendar-color' => '#' . $this->board->getColor(),
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO']),
		];
	}

	private function extractUserIdFromPrincipalUri(): string {
		if (preg_match('#^/?principals/users/([^/]+)$#', $this->principalUri, $matches) !== 1) {
			throw new InvalidDataException('Invalid principal URI');
		}

		return $matches[1];
	}

	private function normalizeCalendarObjectName(string $name): string {
		return $name;
	}

	private function extractCardIdFromNormalizedName(string $name): ?int {
		if (preg_match('/^(?:deck-)?card-(\d+)\.ics$/', $name, $matches) === 1) {
			return (int)$matches[1];
		}

		return null;
	}

	private function isMatchingCalendarObjectName(string $requestedName, string $canonicalName): bool {
		if ($requestedName === $canonicalName) {
			return true;
		}

		if (str_starts_with($requestedName, 'deck-') && substr($requestedName, 5) === $canonicalName) {
			return true;
		}

		return str_starts_with($canonicalName, 'deck-') && substr($canonicalName, 5) === $requestedName;
	}

	/**
	 * Prevent full REPORT failures on stale hrefs by returning a minimal placeholder
	 * object when clients request no-longer-existing calendar object names.
	 *
	 * @return Card|Stack|null
	 */
	private function buildPlaceholderCalendarObject(string $name) {
		if (preg_match('/^(?:deck-)?card-(\d+)\.ics$/', $name, $matches) === 1) {
			$card = new Card();
			$card->setId((int)$matches[1]);
			$card->setTitle('Deleted task');
			$card->setDescription('');
			$card->setType('plain');
			$card->setOrder(0);
			$card->setOwner($this->extractUserIdFromPrincipalUri());
			$card->setStackId($this->resolveFallbackStackId());
			$card->setCreatedAt(time());
			$card->setLastModified(time());
			$card->setDeletedAt(time());
			return $card;
		}

		if (preg_match('/^stack-(\d+)\.ics$/', $name, $matches) === 1) {
			$stack = new Stack();
			$stack->setId((int)$matches[1]);
			$stack->setTitle('Deleted list');
			$stack->setBoardId($this->board->getId());
			$stack->setOrder(0);
			$stack->setDeletedAt(time());
			$stack->setLastModified(time());
			return $stack;
		}

		return null;
	}

	private function resolveFallbackStackId(): int {
		if ($this->stack !== null) {
			return $this->stack->getId();
		}

		$stacks = $this->backend->getStacks($this->board->getId());
		if (count($stacks) > 0) {
			return $stacks[0]->getId();
		}

		return 0;
	}

	private function shouldUsePlaceholderForMissingObject(): bool {
		if (!class_exists('\OC')) {
			return false;
		}

		try {
			$request = \OC::$server->getRequest();
			$method = strtoupper((string)$request->getMethod());
			return in_array($method, ['GET', 'HEAD', 'REPORT', 'PROPFIND'], true);
		} catch (\Throwable $e) {
			return false;
		}
	}
}
