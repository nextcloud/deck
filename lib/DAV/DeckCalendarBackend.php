<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\DAV;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\StackService;
use Sabre\DAV\Exception\NotFound;
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
	/** @var array<int, array<int, bool>> */
	private $permissionCache = [];

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
		if (!isset($this->permissionCache[$id])) {
			$this->permissionCache[$id] = $this->permissionService->getPermissions($id);
		}

		return $this->permissionCache[$id][$permission] ?? false;
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

	public function updateCardFromCalendarObject(Card $sourceCard, string $data): Card {
		$todo = $this->extractTodo($data);
		$card = $this->cardService->find($sourceCard->getId());

		$title = trim((string)($todo->SUMMARY ?? ''));
		if ($title === '') {
			$title = $card->getTitle();
		}

		$description = isset($todo->DESCRIPTION) ? (string)$todo->DESCRIPTION : $card->getDescription();
		$dueDate = isset($todo->DUE) ? $todo->DUE->getDateTime()->format('c') : null;
		$startDate = $card->getStartdate() ? $card->getStartdate()->format('c') : null;

		return $this->cardService->update(
			id: $card->getId(),
			title: $title,
			stackId: $card->getStackId(),
			type: $card->getType(),
			owner: $card->getOwner() ?? '',
			description: $description,
			order: $card->getOrder(),
			duedate: $dueDate,
			deletedAt: $card->getDeletedAt(),
			archived: $card->getArchived(),
			done: $this->mapDoneFromTodo($todo, $card),
			startdate: $startDate,
			color: $card->getColor()
		);
	}

	private function extractTodo(string $data): VTodo {
		try {
			$vObject = Reader::read($data);
		} catch (\Exception $e) {
			throw new InvalidDataException('Invalid calendar payload', 0, $e);
		}

		$todos = $vObject->select('VTODO');
		if (count($todos) !== 1 || !($todos[0] instanceof VTodo)) {
			throw new InvalidDataException('Calendar payload must contain exactly one VTODO');
		}

		return $todos[0];
	}

	private function mapDoneFromTodo(VTodo $todo, Card $card): OptionalNullableValue {
		$done = $card->getDone();
		$percentComplete = isset($todo->{'PERCENT-COMPLETE'}) ? (int)((string)$todo->{'PERCENT-COMPLETE'}) : null;
		$status = isset($todo->STATUS) ? strtoupper((string)$todo->STATUS) : null;

		// Deck only has a binary done state. IN-PROCESS maps to not done;
		// statuses without a Deck equivalent, such as CANCELLED, keep the
		// existing done value instead of inventing a new state.
		if ($status === 'COMPLETED') {
			$done = $this->computeDoneTimestamp($todo);
		} elseif ($status === 'NEEDS-ACTION' || $status === 'IN-PROCESS') {
			$done = null;
		} elseif ($status === null) {
			if (isset($todo->COMPLETED) || ($percentComplete !== null && $percentComplete >= 100)) {
				$done = $this->computeDoneTimestamp($todo);
			} elseif ($percentComplete !== null && $percentComplete === 0) {
				$done = null;
			}
		}

		return new OptionalNullableValue($done);
	}

	private function computeDoneTimestamp(VTodo $todo): \DateTime {
		return isset($todo->COMPLETED)
			? \DateTime::createFromInterface($todo->COMPLETED->getDateTime())
			: new \DateTime();
	}
}
