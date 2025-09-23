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
	private StackMapper $stackMapper;
	private CardMapper $cardMapper;
	private BoardMapper $boardMapper;
	private LabelMapper $labelMapper;
	private PermissionService $permissionService;
	private BoardService $boardService;
	private CardService $cardService;
	private AssignmentMapper $assignedUsersMapper;
	private AttachmentService $attachmentService;
	private ActivityManager $activityManager;
	private ChangeHelper $changeHelper;
	private LoggerInterface $logger;
	private IEventDispatcher $eventDispatcher;
	private StackServiceValidator $stackServiceValidator;

	public function __construct(
		StackMapper $stackMapper,
		BoardMapper $boardMapper,
		CardMapper $cardMapper,
		LabelMapper $labelMapper,
		PermissionService $permissionService,
		BoardService $boardService,
		CardService $cardService,
		AssignmentMapper $assignedUsersMapper,
		AttachmentService $attachmentService,
		ActivityManager $activityManager,
		ChangeHelper $changeHelper,
		LoggerInterface $logger,
		IEventDispatcher $eventDispatcher,
		StackServiceValidator $stackServiceValidator,
	) {
		$this->stackMapper = $stackMapper;
		$this->boardMapper = $boardMapper;
		$this->cardMapper = $cardMapper;
		$this->labelMapper = $labelMapper;
		$this->permissionService = $permissionService;
		$this->boardService = $boardService;
		$this->cardService = $cardService;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->attachmentService = $attachmentService;
		$this->activityManager = $activityManager;
		$this->changeHelper = $changeHelper;
		$this->logger = $logger;
		$this->eventDispatcher = $eventDispatcher;
		$this->stackServiceValidator = $stackServiceValidator;
	}

	/** @param Stack[] $stacks */
	private function enrichStacksWithCards(array $stacks, $since = -1): void {
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

		$cards = array_map(
			function (Card $card): CardDetails {
				$assignedUsers = $this->assignedUsersMapper->findAll($card->getId());
				$card->setAssignedUsers($assignedUsers);
				$card->setAttachmentCount($this->attachmentService->count($card->getId()));

				return new CardDetails($card);
			},
			$this->cardMapper->findAll($stackId)
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
		foreach ($stacks as $stackIndex => $stack) {
			$cards = $this->cardMapper->findAllArchived($stack->id);
			foreach ($cards as $cardIndex => $card) {
				if (array_key_exists($card->id, $labels)) {
					$cards[$cardIndex]->setLabels($labels[$card->id]);
				}
				$cards[$cardIndex]->setAttachmentCount($this->attachmentService->count($card->getId()));
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
			ActivityManager::DECK_OBJECT_BOARD, $stack, ActivityManager::SUBJECT_STACK_CREATE
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
			ActivityManager::DECK_OBJECT_BOARD, $stack, ActivityManager::SUBJECT_STACK_DELETE
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
}
