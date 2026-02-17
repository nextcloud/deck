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

	/** @psalm-suppress InvalidThrow */
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

	/**
	 * Resolve a calendar object id from a CalDAV resource name, optionally
	 * constrained to the current board/stack context.
	 *
	 * @param string $name resource name like card-123.ics, deck-card-123.ics or stack-12.ics
	 * @return Card|Stack|null
	 */
	public function findCalendarObjectByName(string $name, ?int $boardId = null, ?int $stackId = null) {
		if (preg_match('/^(?:deck-)?card-(\d+)\.ics$/', $name, $matches) === 1) {
			$card = $this->findCardByIdIncludingDeleted((int)$matches[1]);
			if ($card === null) {
				return null;
			}

			try {
				if ($stackId !== null && $card->getStackId() !== $stackId) {
					return null;
				}
				if ($boardId !== null && $this->getBoardIdForCard($card) !== $boardId) {
					return null;
				}
			} catch (\Throwable $e) {
				return null;
			}

			return $card;
		}

		if (preg_match('/^stack-(\d+)\.ics$/', $name, $matches) === 1) {
			try {
				$stack = $this->stackService->find((int)$matches[1]);
				if ($boardId !== null && $stack->getBoardId() !== $boardId) {
					return null;
				}

				return $stack;
			} catch (\Throwable $e) {
				return null;
			}
		}

		return null;
	}

	/** @return Card[] */
	public function getChildrenForStack(int $stackId): array {
		return $this->stackService->find($stackId)->getCards() ?? [];
	}

	public function getCalDavListMode(): string {
		return $this->configService->getCalDavListMode();
	}

	public function getCalendarRevisionFingerprint(int $boardId, ?int $stackId = null): string {
		$mode = $this->configService->getCalDavListMode();
		$fingerprint = [$mode];
		$stacks = $this->stackService->findAll($boardId);
		usort($stacks, static fn (Stack $a, Stack $b) => $a->getOrder() <=> $b->getOrder());

		if ($mode === ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_PRIORITY) {
			foreach ($stacks as $stack) {
				$fingerprint[] = $stack->getId() . ':' . $stack->getOrder() . ':' . $stack->getDeletedAt();
			}
		}

		if ($mode === ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_CATEGORY) {
			foreach ($stacks as $stack) {
				$fingerprint[] = $stack->getId() . ':' . $stack->getTitle() . ':' . $stack->getDeletedAt();
			}
		}

		if ($stackId !== null) {
			$fingerprint[] = 'stack:' . $stackId;
		}

		return implode('|', $fingerprint);
	}

	/**
	 * @param Card|Stack $sourceItem
	 */
	public function getObjectRevisionFingerprint($sourceItem): string {
		$mode = $this->configService->getCalDavListMode();
		if (!($sourceItem instanceof Card)) {
			return $mode;
		}

		try {
			$stack = $this->stackService->find($sourceItem->getStackId());
			$boardId = $stack->getBoardId();
		} catch (\Throwable $e) {
			return $mode;
		}

		$fingerprint = [$mode, 'stack:' . $stack->getId()];
		if ($mode === ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_CATEGORY) {
			$fingerprint[] = $stack->getTitle();
			$fingerprint[] = (string)$stack->getDeletedAt();
		}

		if ($mode === ConfigService::SETTING_CALDAV_LIST_MODE_LIST_AS_PRIORITY) {
			$fingerprint[] = $this->getCalendarRevisionFingerprint($boardId);
		}

		return implode('|', $fingerprint);
	}

	public function createCalendarObject(int $boardId, string $owner, string $data, ?int $preferredCardId = null, ?int $preferredStackId = null): Card {
		$todo = $this->extractTodo($data);
		$existingCard = $this->findExistingCardByUid($todo);
		if ($existingCard !== null) {
			$existingBoardId = $this->getBoardIdForCardOrNull($existingCard);
			if ($existingBoardId === null || $existingBoardId === $boardId) {
				$restoreDeleted = $existingCard->getDeletedAt() > 0;
				return $this->updateCardFromCalendar($existingCard, $data, $restoreDeleted, $boardId);
			}
		}

		if ($preferredCardId !== null) {
			$cardById = $this->findCardByIdIncludingDeleted($preferredCardId);
			if ($cardById !== null) {
				$existingBoardId = $this->getBoardIdForCardOrNull($cardById);
				if ($existingBoardId === null || $existingBoardId === $boardId) {
					$restoreDeleted = $cardById->getDeletedAt() > 0;
					return $this->updateCardFromCalendar($cardById, $data, $restoreDeleted, $boardId);
				}
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

		throw new \InvalidArgumentException('Unsupported calendar object source item');
	}

	/**
	 * @param Card|Stack $sourceItem
	 */
	public function deleteCalendarObject($sourceItem, ?int $expectedBoardId = null): void {
		if ($sourceItem instanceof Card) {
			$currentCard = $sourceItem;
			if ($expectedBoardId !== null) {
				try {
					$currentCard = $this->cardService->find($sourceItem->getId());
					$currentBoardId = $this->getBoardIdForCard($currentCard);
					if ($currentBoardId !== $expectedBoardId) {
						// Ignore trailing delete from source calendar after a cross-board move.
						return;
					}
				} catch (\Throwable $e) {
					// If we cannot resolve the current card, continue with normal delete behavior.
				}
			}

			$this->cardService->delete($sourceItem->getId());
			return;
		}

		if ($sourceItem instanceof Stack) {
			$this->stackService->delete($sourceItem->getId());
			return;
		}

		throw new \InvalidArgumentException('Unsupported calendar object source item');
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
		$incomingDue = isset($todo->DUE) ? $todo->DUE->getDateTime() : null;

		$isNoopUpdate = $title === $card->getTitle()
			&& $stackId === $card->getStackId()
			&& $this->normalizeDescriptionForCompare($description) === $this->normalizeDescriptionForCompare((string)$card->getDescription())
			&& $this->isDateEqual($card->getDuedate(), $incomingDue)
			&& $this->isDateEqual($card->getDone(), $done->getValue())
			&& (!$restoreDeleted || $card->getDeletedAt() === 0);

		if ($isNoopUpdate) {
			return $card;
		}

		$updatedCard = $this->cardService->update(
			$card->getId(),
			$title,
			$stackId,
			$card->getType(),
			$card->getOwner() ?? '',
			$description,
			$card->getOrder(),
			$incomingDue ? $incomingDue->format('c') : null,
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
	public function decorateCalendarObject($sourceItem, $calendarObject): void {
		if (!($sourceItem instanceof Card) && !($sourceItem instanceof Stack)) {
			return;
		}

		$todos = $calendarObject->select('VTODO');
		if (count($todos) === 0 || !$this->isSabreVTodo($todos[0])) {
			return;
		}

		$todo = $todos[0];
		$mode = $this->configService->getCalDavListMode();
		if ($sourceItem instanceof Card) {
			$stack = $this->stackService->find($sourceItem->getStackId());
		} else {
			$stack = $sourceItem;
		}

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
			$title = mb_substr($title, mb_strlen('List : '));
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

	private function extractTodo(string $data) {
		$vObject = \Sabre\VObject\Reader::read($data);
		if (!$this->isSabreVCalendar($vObject)) {
			throw new \InvalidArgumentException('Invalid calendar payload');
		}

		$todos = $vObject->select('VTODO');
		if (count($todos) === 0 || !$this->isSabreVTodo($todos[0])) {
			throw new \InvalidArgumentException('Calendar payload contains no VTODO');
		}
		return $todos[0];
	}

	private function extractStackIdFromRelatedTo($todo): ?int {
		$parentCandidates = [];
		$otherCandidates = [];
		foreach ($todo->children() as $child) {
			if (!is_object($child) || !property_exists($child, 'name') || $child->name !== 'RELATED-TO') {
				continue;
			}

			$value = trim($this->toStringValue($child));
			if ($value === '') {
				continue;
			}

			$reltypeValue = $this->getPropertyParameter($child, 'RELTYPE');
			$reltype = $reltypeValue !== null ? strtoupper($reltypeValue) : null;
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

	private function mapDoneFromTodo($todo, Card $card): OptionalNullableValue {
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

	private function inferStackIdFromTodoHints(int $boardId, $todo, string $mode): ?int {
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
			$key = mb_strtolower(trim($stack->getTitle()));
			if ($key !== '') {
				$stackTitles[$key] = true;
			}
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

		// Priority mapping for list mode: left-most list = high priority (9), right-most list = low priority (1)
		$targetIndex = (int)round((9 - $priority) * (count($stacks) - 1) / 8);
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

		// Priority mapping for list mode: left-most list = high priority (9), right-most list = low priority (1)
		return max(1, min(9, 9 - (int)round($index * 8 / (count($stacks) - 1))));
	}

	private function addTodoCategory($todo, string $category): void {
		$category = trim($category);
		if ($category === '') {
			return;
		}

		$current = [];
		foreach ($todo->select('CATEGORIES') as $property) {
			if (is_object($property) && method_exists($property, 'getParts')) {
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
	private function extractCategories($todo): ?array {
		$hasCategories = isset($todo->CATEGORIES);
		$hasAppleTags = false;
		foreach ($todo->children() as $child) {
			if (is_object($child) && property_exists($child, 'name') && strtoupper((string)$child->name) === 'X-APPLE-TAGS') {
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
				return is_object($child) && property_exists($child, 'name') && strtoupper((string)$child->name) === 'X-APPLE-TAGS';
			}))
		);
		foreach ($properties as $property) {
			if (is_object($property) && method_exists($property, 'getParts')) {
				$parts = $property->getParts();
			} else {
				$parts = explode(',', $this->toStringValue($property));
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
			throw new \InvalidArgumentException('No stack available for board');
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

	private function getBoardIdForCardOrNull(Card $card): ?int {
		try {
			return $this->getBoardIdForCard($card);
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function findExistingCardByUid($todo): ?Card {
		$cardIdFromDeckProperty = $this->extractDeckCardId($todo);
		if ($cardIdFromDeckProperty !== null) {
			return $this->findCardByIdIncludingDeleted($cardIdFromDeckProperty);
		}

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

	private function extractDeckCardId($todo): ?int {
		$propertyNames = [
			'X-NC-DECK-CARD-ID',
			'X-NEXTCLOUD-DECK-CARD-ID',
		];

		foreach ($propertyNames as $propertyName) {
			if (!isset($todo->{$propertyName})) {
				continue;
			}

			$value = trim((string)$todo->{$propertyName});
			if (preg_match('/^\d+$/', $value) === 1) {
				return (int)$value;
			}
		}

		return null;
	}

	private function findCardByIdIncludingDeleted(int $cardId): ?Card {
		try {
			return $this->cardService->findIncludingDeleted($cardId);
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function normalizeDescriptionForCompare(string $value): string {
		return str_replace(["\r\n", "\r"], "\n", $value);
	}

	private function isDateEqual(?\DateTimeInterface $left, ?\DateTimeInterface $right): bool {
		if ($left === null && $right === null) {
			return true;
		}
		if ($left === null || $right === null) {
			return false;
		}

		return $left->getTimestamp() === $right->getTimestamp();
	}

	private function isSabreVCalendar($value): bool {
		return $value instanceof VCalendar;
	}

	private function isSabreVTodo($value): bool {
		return $value instanceof VTodo;
	}

	private function getPropertyParameter($property, string $parameter): ?string {
		if (!is_object($property) || !($property instanceof \ArrayAccess) || !isset($property[$parameter])) {
			return null;
		}

		$value = $property[$parameter];
		$string = trim($this->toStringValue($value));
		return $string !== '' ? $string : null;
	}

	private function toStringValue($value): string {
		if (is_scalar($value)) {
			return (string)$value;
		}
		if (is_object($value) && method_exists($value, '__toString')) {
			return (string)$value;
		}

		return '';
	}

}
