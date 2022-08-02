<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Maxence Lange <maxence@artificial-owl.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
use OCA\Deck\Model\CardDetails;
use OCA\Deck\NoPermissionException;
use OCA\Deck\StatusException;
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
		LoggerInterface $logger
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
	}

	private function enrichStackWithCards($stack, $since = -1) {
		$cards = $this->cardMapper->findAll($stack->getId(), null, null, $since);

		if (\count($cards) === 0) {
			return;
		}

		$cards = array_map(
			function (Card $card): CardDetails {
				$this->cardService->enrich($card);
				return new CardDetails($card);
			},
			$cards
		);

		$stack->setCards($cards);
	}

	private function enrichStacksWithCards($stacks, $since = -1) {
		foreach ($stacks as $stack) {
			$this->enrichStackWithCards($stack, $since);
		}
	}

	/**
	 * @param $stackId
	 *
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function find($stackId) {
		if (is_numeric($stackId) === false) {
			throw new BadRequestException('stack id must be a number');
		}

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
	 * @param $boardId
	 *
	 * @return array
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws BadRequestException
	 */
	public function findAll($boardId, $since = -1) {
		if (is_numeric($boardId) === false) {
			throw new BadRequestException('boardId must be a number');
		}

		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_READ);
		$stacks = $this->stackMapper->findAll($boardId);
		$this->enrichStacksWithCards($stacks, $since);

		return $stacks;
	}

	public function findCalendarEntries($boardId) {
		try {
			$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_READ);
		} catch (NoPermissionException $e) {
			$this->logger->error('Unable to check permission for a previously obtained board ' . $boardId, ['exception' => $e]);
			return [];
		}
		return $this->stackMapper->findAll($boardId);
	}

	public function fetchDeleted($boardId) {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		$stacks = $this->stackMapper->findDeleted($boardId);
		$this->enrichStacksWithCards($stacks);

		return $stacks;
	}

	/**
	 * @param $boardId
	 *
	 * @return array
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws BadRequestException
	 */
	public function findAllArchived($boardId) {
		if (is_numeric($boardId) === false) {
			throw new BadRequestException('board id must be a number');
		}

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

		return $stacks;
	}

	/**
	 * @param $title
	 * @param $boardId
	 * @param integer $order
	 *
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function create($title, $boardId, $order) {
		if ($title === false || $title === null || mb_strlen($title) === 0) {
			throw new BadRequestException('title must be provided');
		}

		if (is_numeric($order) === false) {
			throw new BadRequestException('order must be a number');
		}

		if (is_numeric($boardId) === false) {
			throw new BadRequestException('board id must be a number');
		}

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

		return $stack;
	}

	/**
	 * @param $id
	 *
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete($id) {
		if (is_numeric($id) === false) {
			throw new BadRequestException('stack id must be a number');
		}

		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_MANAGE);

		$stack = $this->stackMapper->find($id);
		$stack->setDeletedAt(time());
		$stack = $this->stackMapper->update($stack);

		$this->activityManager->triggerEvent(
			ActivityManager::DECK_OBJECT_BOARD, $stack, ActivityManager::SUBJECT_STACK_DELETE
		);
		$this->changeHelper->boardChanged($stack->getBoardId());
		$this->enrichStackWithCards($stack);

		return $stack;
	}

	/**
	 * @param $id
	 * @param $title
	 * @param $boardId
	 * @param $order
	 * @param $deletedAt
	 *
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update($id, $title, $boardId, $order, $deletedAt) {
		if (is_numeric($id) === false) {
			throw new BadRequestException('stack id must be a number');
		}

		if ($title === false || $title === null || mb_strlen($title) === 0) {
			throw new BadRequestException('title must be provided');
		}

		if (is_numeric($boardId) === false) {
			throw new BadRequestException('board id must be a number');
		}

		if (is_numeric($order) === false) {
			throw new BadRequestException('order must be a number');
		}

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

		return $stack;
	}

	/**
	 * @param $id
	 * @param $order
	 *
	 * @return array
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function reorder($id, $order) {
		if (is_numeric($id) === false) {
			throw new BadRquestException('id must be a number');
		}

		if ($order === false || $order === null) {
			throw new BadRequestException('order must be provided');
		}

		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_MANAGE);
		$stackToSort = $this->stackMapper->find($id);
		$stacks = $this->stackMapper->findAll($stackToSort->getBoardId());
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
			$this->stackMapper->update($stack);
			$result[$stack->getOrder()] = $stack;
		}
		$this->changeHelper->boardChanged($stackToSort->getBoardId());

		return $result;
	}
}
