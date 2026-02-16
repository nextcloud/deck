<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\DAV;

use OCA\Deck\Db\Card;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\StackService;
use Sabre\DAV\Exception\NotFound;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Reader;

class DeckCalendarBackend {

	/** @var BoardService */
	private $boardService;
	/** @var StackService */
	private $stackService;
	/** @var CardService */
	private $cardService;
	/** @var PermissionService */
	private $permissionService;
	/** @var BoardMapper */
	private $boardMapper;

	public function __construct(
		BoardService $boardService, StackService $stackService, CardService $cardService, PermissionService $permissionService,
		BoardMapper $boardMapper,
	) {
		$this->boardService = $boardService;
		$this->stackService = $stackService;
		$this->cardService = $cardService;
		$this->permissionService = $permissionService;
		$this->boardMapper = $boardMapper;
	}

	public function getBoards(): array {
		return $this->boardService->findAll(-1, false, false);
	}

	public function getBoard(int $id): Board {
		try {
			return $this->boardService->find($id);
		} catch (\Exception $e) {
			throw new NotFound('Board with id ' . $id . ' not found');
		}
	}

	public function checkBoardPermission(int $id, int $permission): bool {
		$permissions = $this->permissionService->getPermissions($id);
		return isset($permissions[$permission]) ? $permissions[$permission] : false;
	}

	public function updateBoard(Board $board): bool {
		$this->boardMapper->update($board);
		return true;
	}

	public function getChildren(int $id): array {
		return array_merge(
			$this->cardService->findCalendarEntries($id),
			$this->stackService->findCalendarEntries($id)
		);
	}

	/**
	 * @param Card|Stack $sourceItem
	 * @return Card|Stack
	 */
	public function updateCalendarObject($sourceItem, string $data) {
		if ($sourceItem instanceof Card) {
			return $this->updateCardFromCalendar($sourceItem, $data);
		}

		if ($sourceItem instanceof Stack) {
			return $this->updateStackFromCalendar($sourceItem, $data);
		}

		throw new InvalidDataException('Unsupported calendar object source item');
	}

	/**
	 * @param Card|Stack $sourceItem
	 */
	public function deleteCalendarObject($sourceItem): void {
		if ($sourceItem instanceof Card) {
			$this->cardService->delete($sourceItem->getId());
			return;
		}

		if ($sourceItem instanceof Stack) {
			$this->stackService->delete($sourceItem->getId());
			return;
		}

		throw new InvalidDataException('Unsupported calendar object source item');
	}

	private function updateCardFromCalendar(Card $sourceItem, string $data): Card {
		$todo = $this->extractTodo($data);
		$card = $this->cardService->find($sourceItem->getId());

		$title = trim((string)($todo->SUMMARY ?? ''));
		if ($title === '') {
			$title = $card->getTitle();
		}

		$description = isset($todo->DESCRIPTION) ? (string)$todo->DESCRIPTION : $card->getDescription();
		$stackId = $this->extractStackIdFromRelatedTo($todo) ?? $card->getStackId();
		$done = $this->mapDoneFromTodo($todo, $card);

		return $this->cardService->update(
			$card->getId(),
			$title,
			$stackId,
			$card->getType(),
			$card->getOwner() ?? '',
			$description,
			$card->getOrder(),
			isset($todo->DUE) ? $todo->DUE->getDateTime()->format('c') : null,
			$card->getDeletedAt(),
			$card->getArchived(),
			$done
		);
	}

	private function updateStackFromCalendar(Stack $sourceItem, string $data): Stack {
		$todo = $this->extractTodo($data);
		$stack = $this->stackService->find($sourceItem->getId());

		$title = trim((string)($todo->SUMMARY ?? ''));
		if (mb_strpos($title, 'List : ') === 0) {
			$title = mb_substr($title, strlen('List : '));
		}
		if ($title === '') {
			$title = $stack->getTitle();
		}

		return $this->stackService->update(
			$stack->getId(),
			$title,
			$stack->getBoardId(),
			$stack->getOrder(),
			$stack->getDeletedAt()
		);
	}

	private function extractTodo(string $data): VTodo {
		$vObject = Reader::read($data);
		if (!($vObject instanceof VCalendar)) {
			throw new InvalidDataException('Invalid calendar payload');
		}

		$todos = $vObject->select('VTODO');
		if (count($todos) === 0 || !($todos[0] instanceof VTodo)) {
			throw new InvalidDataException('Calendar payload contains no VTODO');
		}
		return $todos[0];
	}

	private function extractStackIdFromRelatedTo(VTodo $todo): ?int {
		if (!isset($todo->{'RELATED-TO'})) {
			return null;
		}

		$relatedTo = trim((string)$todo->{'RELATED-TO'});
		if (preg_match('/^deck-stack-(\d+)$/', $relatedTo, $matches) === 1) {
			return (int)$matches[1];
		}

		return null;
	}

	private function mapDoneFromTodo(VTodo $todo, Card $card): OptionalNullableValue {
		$done = $card->getDone();
		if (!isset($todo->STATUS) && !isset($todo->COMPLETED)) {
			return new OptionalNullableValue($done);
		}

		$status = isset($todo->STATUS) ? strtoupper((string)$todo->STATUS) : null;
		if ($status === 'COMPLETED' || isset($todo->COMPLETED)) {
			if (isset($todo->COMPLETED)) {
				$completed = $todo->COMPLETED->getDateTime();
				$done = new \DateTime($completed->format('c'));
			} else {
				$done = new \DateTime();
			}
		} elseif ($status === 'NEEDS-ACTION' || $status === 'IN-PROCESS') {
			$done = null;
		}

		return new OptionalNullableValue($done);
	}
}
