<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Activity\ChangeSet;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\BoardUpdatedEvent;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\NoPermissionException;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\StackServiceValidator;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;

class StackService {
	public function __construct(
		private readonly StackMapper $stackMapper,
		private readonly BoardMapper $boardMapper,
		private readonly CardMapper $cardMapper,
		private readonly LabelMapper $labelMapper,
		private readonly PermissionService $permissionService,
		private readonly BoardService $boardService,
		private readonly CardService $cardService,
		private readonly LabelService $labelService,
		private readonly AssignmentMapper $assignedUsersMapper,
		private readonly AttachmentService $attachmentService,
		private readonly ActivityManager $activityManager,
		private readonly ChangeHelper $changeHelper,
		private readonly LoggerInterface $logger,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly StackServiceValidator $stackServiceValidator,
	) {
	}

	/** @param Stack[] $stacks */
	private function enrichStacksWithCards(array $stacks, int $since = -1): void {
		$cardsByStackId = $this->cardMapper->findAllForStacks(array_map(fn (Stack $stack) => $stack->getId(), $stacks), null, 0, $since);

		foreach ($cardsByStackId as $stackId => $cards) {
			if (!$cards) {
				continue;
			}

			foreach ($stacks as $stack) {
				if ($stack->getId() === $stackId) {
					$stack->setCards($this->cardService->enrichCards($cards));
					break;
				}
			}
		}
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function find(int $stackId): Stack {
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_READ);
		$stack = $this->stackMapper->find($stackId);

		$allCards = $this->cardMapper->findAll($stackId);
		$cardIds = array_map(fn (Card $card) => $card->getId(), $allCards);
		$attachmentCounts = $this->attachmentService->countForCards($cardIds);
		$assignedUsers = $this->assignedUsersMapper->findIn($cardIds);

		$cards = array_map(
			function (Card $card) use ($attachmentCounts, $assignedUsers): CardDetails {
				$cardAssignedUsers = array_values(array_filter($assignedUsers, fn ($a) => $a->getCardId() === $card->getId()));
				$card->setAssignedUsers($cardAssignedUsers);
				$card->setAttachmentCount($attachmentCounts[$card->getId()] ?? 0);

				return new CardDetails($card);
			},
			$allCards
		);

		$stack->setCards($cards);

		return $stack;
	}

	/**
	 * @return Stack[]
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws BadRequestException
	 */
	public function findAll(int $boardId, int $since = -1): array {
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_READ);
		$stacks = $this->stackMapper->findAll($boardId);
		$this->enrichStacksWithCards($stacks, $since);

		return $stacks;
	}

	/**
	 * @return Stack[]
	 * @throws \OCP\DB\Exception
	 */
	public function findCalendarEntries(int $boardId): array {
		try {
			$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_READ);
		} catch (NoPermissionException $e) {
			$this->logger->error('Unable to check permission for a previously obtained board ' . $boardId, ['exception' => $e]);
			return [];
		}
		return $this->stackMapper->findAll($boardId);
	}

	/**
	 * @return Stack[]
	 * @throws \OCP\DB\Exception
	 */
	public function fetchDeleted(int $boardId): array {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		$stacks = $this->stackMapper->findDeleted($boardId);
		$this->enrichStacksWithCards($stacks);

		return $stacks;
	}

	/**
	 * @return Stack[]
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws BadRequestException
	 */
	public function findAllArchived(int $boardId): array {
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_READ);
		$stacks = $this->stackMapper->findAll($boardId);
		$labels = $this->labelMapper->getAssignedLabelsForBoard($boardId);

		$stackIds = array_map(fn (Stack $stack) => $stack->getId(), $stacks);

		// Fetch all archived cards for all stacks in a single query
		$cardsByStackId = $this->cardMapper->findAllArchivedForStacks($stackIds);

		$allArchivedCardIds = [];
		foreach ($cardsByStackId as $cards) {
			foreach ($cards as $card) {
				$allArchivedCardIds[] = $card->getId();
			}
		}

		$attachmentCounts = $this->attachmentService->countForCards($allArchivedCardIds);

		foreach ($stacks as $stackIndex => $stack) {
			$cards = $cardsByStackId[$stack->getId()] ?? [];
			foreach ($cards as $cardIndex => $card) {
				if (array_key_exists($card->id, $labels)) {
					$cards[$cardIndex]->setLabels($labels[$card->id]);
				}
				$cards[$cardIndex]->setAttachmentCount($attachmentCounts[$card->getId()] ?? 0);
			}
			$stacks[$stackIndex]->setCards($cards);
		}

		/** @var Stack[] $stacks */
		return $stacks;
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function create(string $title, int $boardId, int $order): Stack {
		$this->stackServiceValidator->check(compact('title', 'boardId', 'order'));

		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		if ($this->boardService->isArchived(null, $boardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$stack = new Stack();
		$stack->setTitle($title);
		$stack->setBoardId($boardId);
		$stack->setOrder($order);
		$stack = $this->stackMapper->insert($stack);
		$this->activityManager->triggerEvent(
			ActivityManager::DECK_OBJECT_BOARD, $stack, ActivityManager::SUBJECT_STACK_CREATE, [], $this->permissionService->getUserId()
		);
		$this->changeHelper->boardChanged($boardId);
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($boardId));

		return $stack;
	}

	/**
	 * @return Stack The deleted stack.
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete(int $id): Stack {
		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_MANAGE);

		$stack = $this->stackMapper->find($id);
		$stack->setDeletedAt(time());
		$stack = $this->stackMapper->update($stack);

		$this->activityManager->triggerEvent(
			ActivityManager::DECK_OBJECT_BOARD, $stack, ActivityManager::SUBJECT_STACK_DELETE, [], $this->permissionService->getUserId()
		);
		$this->changeHelper->boardChanged($stack->getBoardId());
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($stack->getBoardId()));
		$this->enrichStacksWithCards([$stack]);

		return $stack;
	}

	/**
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update(int $id, string $title, int $boardId, int $order, ?int $deletedAt): Stack {
		$this->stackServiceValidator->check(compact('id', 'title', 'boardId', 'order'));

		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_MANAGE);
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_MANAGE);

		if ($this->boardService->isArchived($this->stackMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}

		$stack = $this->stackMapper->find($id);
		$changes = new ChangeSet($stack);
		$stack->setTitle($title);
		$stack->setBoardId($boardId);
		$stack->setOrder($order);
		$stack->setDeletedAt($deletedAt);
		$changes->setAfter($stack);
		$stack = $this->stackMapper->update($stack);
		$this->activityManager->triggerUpdateEvents(
			ActivityManager::DECK_OBJECT_BOARD, $changes, ActivityManager::SUBJECT_STACK_UPDATE
		);
		$this->changeHelper->boardChanged($stack->getBoardId());
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($stack->getBoardId()));

		return $stack;
	}

	/**
	 * @return array<int, Stack> The stacks in the correct order.
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function reorder(int $id, int $order): array {
		$this->stackServiceValidator->check(compact('id', 'order'));

		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_MANAGE);
		$stackToSort = $this->stackMapper->find($id);
		$stacks = $this->stackMapper->findAll($stackToSort->getBoardId());
		usort($stacks, static fn (Stack $stackA, Stack $stackB) => $stackA->getOrder() - $stackB->getOrder());
		$result = [];
		$i = 0;
		foreach ($stacks as $stack) {
			if ($stack->id === $id) {
				$stack->setOrder($order);
			}

			if ($i === $order) {
				$i++;
			}

			if ($stack->id !== $id) {
				$stack->setOrder($i++);
			}
			$stack = $this->stackMapper->update($stack);
			$result[$stack->getOrder()] = $stack;
		}
		$this->changeHelper->boardChanged($stackToSort->getBoardId());
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($stackToSort->getBoardId()));

		return $result;
	}

	/**
	 * Move a stack (with all of its cards) to another board.
	 *
	 * The stack keeps its identity, so the cards keep their comments,
	 * attachments and activity history. Board-specific labels assigned to the
	 * cards are remapped onto the target board by title (creating missing ones
	 * when the user may manage the target board), mirroring the behaviour of
	 * moving a single card across boards.
	 *
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function move(int $id, int $targetBoardId): Stack {
		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_MANAGE);
		$this->permissionService->checkPermission($this->boardMapper, $targetBoardId, Acl::PERMISSION_MANAGE);

		if ($this->boardService->isArchived($this->stackMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		if ($this->boardService->isArchived(null, $targetBoardId)) {
			throw new StatusException('Operation not allowed. The target board is archived.');
		}

		$stack = $this->stackMapper->find($id);
		$sourceBoardId = $stack->getBoardId();
		if ($sourceBoardId === $targetBoardId) {
			$this->enrichStacksWithCards([$stack]);
			return $stack;
		}

		$changes = new ChangeSet($stack);
		// Append the stack at the end of the target board.
		$targetStacks = $this->stackMapper->findAll($targetBoardId);
		$stack->setBoardId($targetBoardId);
		$stack->setOrder(count($targetStacks));
		$stack = $this->stackMapper->update($stack);
		$changes->setAfter($stack);

		// Remap each card's board-specific labels onto the target board.
		foreach ($this->cardMapper->findAllByStack($id) as $card) {
			foreach ($this->labelMapper->findAssignedLabelsForCard($card->getId()) as $label) {
				$this->cardMapper->removeLabel($card->getId(), $label->getId());
				try {
					$newLabel = $this->labelService->cloneLabelIfNotExists($label->getId(), $targetBoardId);
				} catch (NoPermissionException $e) {
					continue;
				}
				$this->cardMapper->assignLabel($card->getId(), $newLabel->getId());
			}
			$this->changeHelper->cardChanged($card->getId());
		}

		$this->activityManager->triggerUpdateEvents(
			ActivityManager::DECK_OBJECT_BOARD, $changes, ActivityManager::SUBJECT_STACK_UPDATE
		);
		$this->changeHelper->boardChanged($sourceBoardId);
		$this->changeHelper->boardChanged($targetBoardId);
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($sourceBoardId));
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($targetBoardId));

		$this->enrichStacksWithCards([$stack]);
		return $stack;
	}

	/**
	 * Copy a stack and all of its cards to another board (which may be the same
	 * board). The new cards are clones, so board-specific labels and
	 * assignments are remapped through {@see CardService::cloneCard()}.
	 *
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function cloneStack(int $id, int $targetBoardId): Stack {
		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_READ);
		$this->permissionService->checkPermission($this->boardMapper, $targetBoardId, Acl::PERMISSION_MANAGE);

		if ($this->boardService->isArchived(null, $targetBoardId)) {
			throw new StatusException('Operation not allowed. The target board is archived.');
		}

		$sourceStack = $this->stackMapper->find($id);
		$targetStacks = $this->stackMapper->findAll($targetBoardId);

		$newStack = new Stack();
		$newStack->setTitle($sourceStack->getTitle());
		$newStack->setBoardId($targetBoardId);
		$newStack->setOrder(count($targetStacks));
		$newStack = $this->stackMapper->insert($newStack);

		foreach ($this->cardMapper->findAllByStack($id) as $card) {
			$this->cardService->cloneCard($card->getId(), $newStack->getId());
		}

		$this->activityManager->triggerEvent(
			ActivityManager::DECK_OBJECT_BOARD, $newStack, ActivityManager::SUBJECT_STACK_CREATE
		);
		$this->changeHelper->boardChanged($targetBoardId);
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($targetBoardId));

		$this->enrichStacksWithCards([$newStack]);
		return $newStack;
	}

	/**
	 * Set or unset a stack as the "done column" for the board
	 *
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function setDoneStack(int $stackId, int $boardId, bool $isDone): void {
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_MANAGE);

		if ($this->boardService->isArchived($this->stackMapper, $stackId)) {
			throw new NoPermissionException('Operation not allowed. This board is archived.');
		}

		if ($isDone) {
			$this->stackMapper->clearDoneColumnForBoard($boardId);
			// Mark all existing cards in the stack as done
			/** @var Card $card */
			foreach ($this->cardMapper->findAll($stackId) as $card) {
				if ($card->getDone() === null) {
					$card->setDone(new \DateTime());
					$this->cardMapper->update($card);
				}
			}
		}

		$this->stackMapper->setIsDoneColumn($stackId, $isDone);
		$this->changeHelper->boardChanged($boardId);
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($boardId));
	}
}
