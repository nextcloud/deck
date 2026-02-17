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
use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\LabelService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\StackService;
use Sabre\DAV\Exception\NotFound;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;
use Sabre\VObject\Property\ICalendar\Categories;
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
	/** @var LabelService */
	private $labelService;
	/** @var ConfigService */
	private $configService;

	public function __construct(
		BoardService $boardService, StackService $stackService, CardService $cardService, PermissionService $permissionService,
		BoardMapper $boardMapper, LabelService $labelService, ConfigService $configService,
	) {
		$this->boardService = $boardService;
		$this->stackService = $stackService;
		$this->cardService = $cardService;
		$this->permissionService = $permissionService;
		$this->boardMapper = $boardMapper;
		$this->labelService = $labelService;
		$this->configService = $configService;
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

	/** @return Stack[] */
	public function getStacks(int $boardId): array {
		return $this->stackService->findCalendarEntries($boardId);
	}

	public function getStack(int $stackId): Stack {
		return $this->stackService->find($stackId);
	}

	/** @return Card[] */
	public function getChildrenForStack(int $stackId): array {
		return $this->stackService->find($stackId)->getCards() ?? [];
	}

	public function createCalendarObject(int $boardId, string $owner, string $data, ?int $preferredCardId = null, ?int $preferredStackId = null): Card {
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

		$mode = $this->configService->getCalDavListMode();
		$relatedStackId = $this->extractStackIdFromRelatedTo($todo);
		if ($relatedStackId === null && $preferredStackId !== null) {
			$relatedStackId = $preferredStackId;
		}
		if ($relatedStackId === null) {
			$relatedStackId = $this->inferStackIdFromTodoHints($boardId, $todo, $mode);
		}
		$stackId = $this->resolveStackIdForBoard($boardId, $relatedStackId);
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
		if ($done !== null) {
			$card = $this->cardService->update(
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

		$categories = $this->extractCategories($todo);
		if ($categories !== null) {
			$categories = $this->normalizeCategoriesForLabelSync($boardId, $categories, $mode);
			$this->syncCardCategories($card->getId(), $categories);
		}

		return $card;
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
		$mode = $this->configService->getCalDavListMode();
		$relatedStackId = $this->extractStackIdFromRelatedTo($todo);
		if ($relatedStackId === null) {
			$relatedStackId = $this->inferStackIdFromTodoHints($boardId, $todo, $mode);
		}
		if ($relatedStackId !== null) {
			$stackId = $this->resolveStackIdForBoard($boardId, $relatedStackId);
		} elseif ($targetBoardId !== null && $currentBoardId !== $targetBoardId) {
			$stackId = $this->getDefaultStackIdForBoard($targetBoardId);
		} else {
			$stackId = $card->getStackId();
		}
		$done = $this->mapDoneFromTodo($todo, $card);

		$updatedCard = $this->cardService->update(
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

		$categories = $this->extractCategories($todo);
		if ($categories !== null) {
			$categories = $this->normalizeCategoriesForLabelSync($boardId, $categories, $mode);
			$this->syncCardCategories($updatedCard->getId(), $categories);
		}

		return $updatedCard;
	}

	/**
	 * @param Card|Stack $sourceItem
	 */
	public function decorateCalendarObject($sourceItem, VCalendar $calendarObject): void {
		if (!($sourceItem instanceof Card)) {
			return;
		}

		$todos = $calendarObject->select('VTODO');
		if (count($todos) === 0 || !($todos[0] instanceof VTodo)) {
			return;
		}

		$todo = $todos[0];
		$mode = $this->configService->getCalDavListMode();
		$stack = $this->stackService->find($sourceItem->getStackId());

		if ($mode === ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_CATEGORY) {
			$this->addTodoCategory($todo, $stack->getTitle());
		}

		if ($mode === ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_PRIORITY) {
			$priority = $this->calculateStackPriority($stack->getBoardId(), $stack->getId());
			$todo->PRIORITY = $priority;
		}
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

	private function inferStackIdFromTodoHints(int $boardId, VTodo $todo, string $mode): ?int {
		if ($mode === ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_CATEGORY) {
			$categories = $this->extractCategories($todo) ?? [];
			return $this->inferStackIdFromCategories($boardId, $categories);
		}

		if ($mode === ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_PRIORITY) {
			$priority = isset($todo->PRIORITY) ? (int)((string)$todo->PRIORITY) : null;
			return $this->inferStackIdFromPriority($boardId, $priority);
		}

		return null;
	}

	/**
	 * @param list<string> $categories
	 * @return list<string>
	 */
	private function normalizeCategoriesForLabelSync(int $boardId, array $categories, string $mode): array {
		if ($mode !== ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_CATEGORY) {
			return $categories;
		}

		$stacks = $this->stackService->findAll($boardId);
		$stackTitles = [];
		foreach ($stacks as $stack) {
			$stackTitles[mb_strtolower(trim($stack->getTitle()))] = true;
		}

		return array_values(array_filter($categories, static function (string $category) use ($stackTitles): bool {
			$key = mb_strtolower(trim($category));
			return $key !== '' && !isset($stackTitles[$key]);
		}));
	}

	private function inferStackIdFromCategories(int $boardId, array $categories): ?int {
		if (count($categories) === 0) {
			return null;
		}

		$categoriesByKey = [];
		foreach ($categories as $category) {
			$key = mb_strtolower(trim($category));
			if ($key !== '') {
				$categoriesByKey[$key] = true;
			}
		}

		foreach ($this->stackService->findAll($boardId) as $stack) {
			$key = mb_strtolower(trim($stack->getTitle()));
			if ($key !== '' && isset($categoriesByKey[$key])) {
				return $stack->getId();
			}
		}

		return null;
	}

	private function inferStackIdFromPriority(int $boardId, ?int $priority): ?int {
		if ($priority === null || $priority < 1 || $priority > 9) {
			return null;
		}

		$stacks = $this->stackService->findAll($boardId);
		if (count($stacks) === 0) {
			return null;
		}
		usort($stacks, static fn (Stack $a, Stack $b) => $a->getOrder() <=> $b->getOrder());

		$targetIndex = (int)round(($priority - 1) * (count($stacks) - 1) / 8);
		return $stacks[max(0, min(count($stacks) - 1, $targetIndex))]->getId();
	}

	private function calculateStackPriority(int $boardId, int $stackId): int {
		$stacks = $this->stackService->findAll($boardId);
		if (count($stacks) <= 1) {
			return 1;
		}

		usort($stacks, static fn (Stack $a, Stack $b) => $a->getOrder() <=> $b->getOrder());
		$index = 0;
		foreach ($stacks as $position => $stack) {
			if ($stack->getId() === $stackId) {
				$index = $position;
				break;
			}
		}

		return max(1, min(9, 1 + (int)round($index * 8 / (count($stacks) - 1))));
	}

	private function addTodoCategory(VTodo $todo, string $category): void {
		$category = trim($category);
		if ($category === '') {
			return;
		}

		$current = [];
		foreach ($todo->select('CATEGORIES') as $property) {
			if ($property instanceof Categories) {
				foreach ($property->getParts() as $part) {
					$key = mb_strtolower(trim((string)$part));
					if ($key !== '') {
						$current[$key] = trim((string)$part);
					}
				}
			}
		}

		$key = mb_strtolower($category);
		if (!isset($current[$key])) {
			$current[$key] = $category;
			$todo->CATEGORIES = array_values($current);
		}
	}

	/**
	 * @return list<string>|null
	 */
	private function extractCategories(VTodo $todo): ?array {
		$hasCategories = isset($todo->CATEGORIES);
		$hasAppleTags = false;
		foreach ($todo->children() as $child) {
			if ($child instanceof Property && strtoupper($child->name) === 'X-APPLE-TAGS') {
				$hasAppleTags = true;
				break;
			}
		}

		if (!$hasCategories && !$hasAppleTags) {
			return null;
		}

		$values = [];
		$properties = array_merge(
			$todo->select('CATEGORIES'),
			array_values(array_filter($todo->children(), static function ($child): bool {
				return $child instanceof Property && strtoupper($child->name) === 'X-APPLE-TAGS';
			}))
		);
		foreach ($properties as $property) {
			if ($property instanceof Categories) {
				$parts = $property->getParts();
			} else {
				$parts = explode(',', (string)$property);
			}
			foreach ($parts as $part) {
				$title = trim((string)$part);
				$title = ltrim($title, '#');
				if ($title !== '') {
					$values[$title] = true;
				}
			}
		}

		return array_keys($values);
	}

	/**
	 * @param list<string> $categories
	 */
	private function syncCardCategories(int $cardId, array $categories): void {
		$card = $this->cardService->find($cardId);
		$boardId = $this->getBoardIdForCard($card);
		$board = $this->boardMapper->find($boardId, true, false);
		$boardLabels = $board->getLabels() ?? [];

		$boardLabelsByTitle = [];
		foreach ($boardLabels as $label) {
			$key = mb_strtolower(trim($label->getTitle()));
			if ($key !== '' && !isset($boardLabelsByTitle[$key])) {
				$boardLabelsByTitle[$key] = $label;
			}
		}

		$targetLabelIds = [];
		foreach ($categories as $category) {
			$title = trim($category);
			$key = mb_strtolower($title);
			if ($key === '' || !isset($boardLabelsByTitle[$key])) {
				$createdLabel = $this->createLabelForCategory($boardId, $title);
				if ($createdLabel !== null) {
					$boardLabelsByTitle[$key] = $createdLabel;
				}
			}
			if (!isset($boardLabelsByTitle[$key])) {
				continue;
			}
			$targetLabelIds[$boardLabelsByTitle[$key]->getId()] = true;
		}

		$currentLabels = $card->getLabels() ?? [];
		$currentLabelIds = [];
		foreach ($currentLabels as $label) {
			$currentLabelIds[$label->getId()] = true;
		}

		foreach (array_keys($currentLabelIds) as $labelId) {
			if (!isset($targetLabelIds[$labelId])) {
				$this->cardService->removeLabel($cardId, $labelId);
			}
		}

		foreach (array_keys($targetLabelIds) as $labelId) {
			if (!isset($currentLabelIds[$labelId])) {
				$this->cardService->assignLabel($cardId, $labelId);
			}
		}
	}

	private function createLabelForCategory(int $boardId, string $title): ?\OCA\Deck\Db\Label {
		$title = trim($title);
		if ($title === '') {
			return null;
		}

		try {
			return $this->labelService->create($title, '31CC7C', $boardId);
		} catch (\Throwable $e) {
			try {
				$board = $this->boardMapper->find($boardId, true, false);
				foreach ($board->getLabels() ?? [] as $label) {
					if (mb_strtolower(trim($label->getTitle())) === mb_strtolower($title)) {
						return $label;
					}
				}
			} catch (\Throwable $ignored) {
			}
		}

		return null;
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
