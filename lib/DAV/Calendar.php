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
	/** @var array<int, Card|Stack>|null */
	private $children = null;
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
		try {
			$this->getChildNode($name, false, false)->put((string)$data);
			$this->children = null;
			return;
		} catch (NotFound $e) {
			// New object path, continue with create.
		}

		$owner = $this->extractUserIdFromPrincipalUri();
		$this->backend->createCalendarObject(
			$this->board->getId(),
			$owner,
			(string)$data,
			$this->extractCardIdFromNormalizedName($name),
			$this->stack?->getId()
		);
		$this->children = null;
	}

	public function getChild($name) {
		return $this->getChildNode($name, true, true);
	}

	private function getChildNode(string $name, bool $allowPlaceholder, bool $includeDeletedFallback) {
		foreach ($this->getBackendChildren() as $item) {
			$canonicalName = $item->getCalendarPrefix() . '-' . $item->getId() . '.ics';
			if ($this->isMatchingCalendarObjectName($name, $canonicalName)) {
				return new CalendarObject($this, $canonicalName, $this->backend, $item);
			}
		}

		// Fallback for stale hrefs that are no longer part of the current
		// children cache but still refer to a board-local object.
		$fallbackItem = $this->backend->findCalendarObjectByName(
			$name,
			$this->board->getId(),
			$this->stack?->getId(),
			$includeDeletedFallback
		);
		if ($fallbackItem !== null) {
			$canonicalName = $fallbackItem->getCalendarPrefix() . '-' . $fallbackItem->getId() . '.ics';
			return new CalendarObject($this, $canonicalName, $this->backend, $fallbackItem);
		}

		if ($allowPlaceholder && $this->shouldUsePlaceholderForMissingObject()) {
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
		foreach ($this->getBackendChildren() as $item) {
			$name = $item->getCalendarPrefix() . '-' . $item->getId() . '.ics';
			$children[] = new CalendarObject($this, $name, $this->backend, $item);
		}

		return $children;
	}

	private function getBackendChildren() {
		if ($this->children !== null) {
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
		return count(array_filter(
			$this->getBackendChildren(),
			function ($item) use (&$name) {
				$canonicalName = $item->getCalendarPrefix() . '-' . $item->getId() . '.ics';
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

	public function getStackId(): ?int {
		return $this->stack?->getId();
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
			$cardId = (int)$matches[1];
			$card = $this->backend->findCalendarObjectByName($name, $this->board->getId(), $this->stack?->getId());
			if (!($card instanceof Card)) {
				// Fallback for stale hrefs after cross-board moves.
				$card = $this->backend->findCalendarObjectByName($name, null, null);
			}
			if (!($card instanceof Card)) {
				return null;
			}

			$placeholder = new Card();
			$placeholder->setId($cardId);
			$placeholder->setTitle('Deleted task');
			$placeholder->setDescription('');
			$placeholder->setStackId($this->stack?->getId() ?? $card->getStackId());
			$cardType = (string)$card->getType();
			$placeholder->setType($cardType !== '' ? $cardType : 'plain');
			$placeholder->setOrder(0);
			$placeholder->setCreatedAt($card->getCreatedAt() > 0 ? $card->getCreatedAt() : time());
			$placeholder->setLastModified(time());
			$placeholder->setDeletedAt(time());
			return $placeholder;
		}

		if (preg_match('/^stack-(\d+)\.ics$/', $name, $matches) === 1) {
			$stackId = (int)$matches[1];
			try {
				$stack = $this->backend->getStack($stackId);
				if ($stack->getBoardId() !== $this->board->getId()) {
					return null;
				}
			} catch (\Throwable $e) {
				return null;
			}

			$stack = new Stack();
			$stack->setId($stackId);
			$stack->setTitle('Deleted list');
			$stack->setBoardId($this->board->getId());
			$stack->setOrder(0);
			$stack->setDeletedAt(time());
			$stack->setLastModified(time());
			return $stack;
		}

		return null;
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
