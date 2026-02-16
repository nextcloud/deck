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
use Sabre\VObject\Property;
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

	public function createCalendarObject(int $boardId, string $owner, string $data, ?int $preferredCardId = null): Card {
		$todo = $this->extractTodo($data);
		$existingCard = $this->findExistingCardByUid($todo);
		if ($existingCard !== null) {
			$restoreDeleted = $existingCard->getDeletedAt() > 0;
			return $this->updateCardFromCalendar($existingCard, $data, $restoreDeleted, $boardId);
		}

		if ($preferredCardId !== null) {
			$cardById = $this->findCardByIdIncludingDeleted($preferredCardId);
			if ($cardById !== null) {
				$restoreDeleted = $cardById->getDeletedAt() > 0;
				return $this->updateCardFromCalendar($cardById, $data, $restoreDeleted, $boardId);
			}
		}

		$title = trim((string)($todo->SUMMARY ?? ''));
		if ($title === '') {
			$title = 'New task';
		}

		$stackId = $this->resolveStackIdForBoard($boardId, $this->extractStackIdFromRelatedTo($todo));
		$description = isset($todo->DESCRIPTION) ? (string)$todo->DESCRIPTION : '';
		$dueDate = isset($todo->DUE) ? new \DateTime($todo->DUE->getDateTime()->format('c')) : null;

		$card = $this->cardService->create(
			$title,
			$stackId,
			'plain',
			999,
			$owner,
			$description,
			$dueDate
		);

		$done = $this->mapDoneFromTodo($todo, $card)->getValue();
		if ($done === null) {
			return $card;
		}

		return $this->cardService->update(
			$card->getId(),
			$card->getTitle(),
			$card->getStackId(),
			$card->getType(),
			$card->getOwner() ?? $owner,
			$card->getDescription(),
			$card->getOrder(),
			$card->getDuedate() ? $card->getDuedate()->format('c') : null,
			$card->getDeletedAt(),
			$card->getArchived(),
			new OptionalNullableValue($done)
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

	private function updateCardFromCalendar(Card $sourceItem, string $data, bool $restoreDeleted = false, ?int $targetBoardId = null): Card {
		$todo = $this->extractTodo($data);
		$card = $restoreDeleted ? $sourceItem : $this->cardService->find($sourceItem->getId());
		$currentBoardId = $this->getBoardIdForCard($card);
		$boardId = $targetBoardId ?? $currentBoardId;

		$title = trim((string)($todo->SUMMARY ?? ''));
		if ($title === '') {
			$title = $card->getTitle();
		}

		$description = isset($todo->DESCRIPTION) ? (string)$todo->DESCRIPTION : $card->getDescription();
		$relatedStackId = $this->extractStackIdFromRelatedTo($todo);
		if ($relatedStackId !== null) {
			$stackId = $this->resolveStackIdForBoard($boardId, $relatedStackId);
		} elseif ($targetBoardId !== null && $currentBoardId !== $targetBoardId) {
			$stackId = $this->getDefaultStackIdForBoard($targetBoardId);
		} else {
			$stackId = $card->getStackId();
		}
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
			$restoreDeleted ? 0 : $card->getDeletedAt(),
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
		$parentCandidates = [];
		$otherCandidates = [];
		foreach ($todo->children() as $child) {
			if (!($child instanceof Property) || $child->name !== 'RELATED-TO') {
				continue;
			}

			$value = trim((string)$child);
			if ($value === '') {
				continue;
			}

			$reltype = isset($child['RELTYPE']) ? strtoupper((string)$child['RELTYPE']) : null;
			if ($reltype === 'PARENT') {
				$parentCandidates[] = $value;
			} else {
				$otherCandidates[] = $value;
			}
		}

		foreach (array_merge($parentCandidates, $otherCandidates) as $candidate) {
			if (preg_match('/^deck-stack-(\d+)$/', $candidate, $matches) === 1) {
				return (int)$matches[1];
			}
		}

		return null;
	}

	private function mapDoneFromTodo(VTodo $todo, Card $card): OptionalNullableValue {
		$done = $card->getDone();
		$percentComplete = isset($todo->{'PERCENT-COMPLETE'}) ? (int)((string)$todo->{'PERCENT-COMPLETE'}) : null;
		if (!isset($todo->STATUS) && !isset($todo->COMPLETED) && $percentComplete === null) {
			return new OptionalNullableValue($done);
		}

		$status = isset($todo->STATUS) ? strtoupper((string)$todo->STATUS) : null;
		if ($status === 'COMPLETED' || isset($todo->COMPLETED) || ($percentComplete !== null && $percentComplete >= 100)) {
			if (isset($todo->COMPLETED)) {
				$completed = $todo->COMPLETED->getDateTime();
				$done = new \DateTime($completed->format('c'));
			} else {
				$done = new \DateTime();
			}
		} elseif ($status === 'NEEDS-ACTION' || $status === 'IN-PROCESS' || ($percentComplete !== null && $percentComplete === 0)) {
			$done = null;
		}

		return new OptionalNullableValue($done);
	}

	private function getDefaultStackIdForBoard(int $boardId): int {
		$stacks = $this->stackService->findAll($boardId);
		if (count($stacks) === 0) {
			throw new InvalidDataException('No stack available for board');
		}

		usort($stacks, static fn (Stack $a, Stack $b) => $a->getOrder() <=> $b->getOrder());
		return $stacks[0]->getId();
	}

	private function resolveStackIdForBoard(int $boardId, ?int $candidateStackId): int {
		if ($candidateStackId === null) {
			return $this->getDefaultStackIdForBoard($boardId);
		}

		try {
			$stack = $this->stackService->find($candidateStackId);
			if ($stack->getBoardId() === $boardId) {
				return $candidateStackId;
			}
		} catch (\Throwable $e) {
			// Fall through to default stack if referenced stack is inaccessible or does not exist.
		}

		return $this->getDefaultStackIdForBoard($boardId);
	}

	private function getBoardIdForCard(Card $card): int {
		$stack = $this->stackService->find($card->getStackId());
		return $stack->getBoardId();
	}

	private function findExistingCardByUid(VTodo $todo): ?Card {
		if (!isset($todo->UID)) {
			return null;
		}

		$uid = trim((string)$todo->UID);
		if (preg_match('/^deck-card-(\d+)$/', $uid, $matches) !== 1) {
			return null;
		}

		$cardId = (int)$matches[1];
		return $this->findCardByIdIncludingDeleted($cardId);
	}

	private function findCardByIdIncludingDeleted(int $cardId): ?Card {
		try {
			return $this->cardService->find($cardId);
		} catch (\Throwable $e) {
			// continue with deleted cards
		}

		foreach ($this->boardService->findAll(-1, false, false) as $board) {
			try {
				foreach ($this->cardService->fetchDeleted($board->getId()) as $deletedCard) {
					if ($deletedCard->getId() === $cardId) {
						return $deletedCard;
					}
				}
			} catch (\Throwable $e) {
				// ignore inaccessible board and continue searching
			}
		}

		return null;
	}
}
