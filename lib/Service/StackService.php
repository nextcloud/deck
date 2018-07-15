<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\StatusException;
use OCP\ICache;
use OCP\ICacheFactory;


class StackService {

	private $stackMapper;
	private $cardMapper;
	private $boardMapper;
	private $labelMapper;
	private $permissionService;
	private $boardService;
	private $assignedUsersMapper;
	private $attachmentService;

	public function __construct(
		StackMapper $stackMapper,
		BoardMapper $boardMapper,
		CardMapper $cardMapper,
		LabelMapper $labelMapper,
		PermissionService $permissionService,
		BoardService $boardService,
		AssignedUsersMapper $assignedUsersMapper,
		AttachmentService $attachmentService
	) {
		$this->stackMapper = $stackMapper;
		$this->boardMapper = $boardMapper;
		$this->cardMapper = $cardMapper;
		$this->labelMapper = $labelMapper;
		$this->permissionService = $permissionService;
		$this->boardService = $boardService;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->attachmentService = $attachmentService;
	}

	public function findAll($boardId) {
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_READ);
		$stacks = $this->stackMapper->findAll($boardId);
		$labels = $this->labelMapper->getAssignedLabelsForBoard($boardId);
		foreach ($stacks as $stackIndex => $stack) {
			$cards = $this->cardMapper->findAll($stack->id);
			foreach ($cards as $cardIndex => $card) {
				$assignedUsers = $this->assignedUsersMapper->find($card->getId());
				$card->setAssignedUsers($assignedUsers);
				if (array_key_exists($card->id, $labels)) {
					$cards[$cardIndex]->setLabels($labels[$card->id]);
				}
				$card->setAttachmentCount($this->attachmentService->count($card->getId()));
			}
			$stacks[$stackIndex]->setCards($cards);
		}
		return $stacks;
	}

	public function fetchDeleted($boardId) {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		return $this->stackMapper->findDeleted($boardId);
	}

	public function findAllArchived($boardId) {
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_READ);
		$stacks = $this->stackMapper->findAll($boardId);
		$labels = $this->labelMapper->getAssignedLabelsForBoard($boardId);
		foreach ($stacks as $stackIndex => $stack) {
			$cards = $this->cardMapper->findAllArchived($stack->id);
			foreach ($cards as $cardIndex => $card) {
				if (array_key_exists($card->id, $labels)) {
					$cards[$cardIndex]->setLabels($labels[$card->id]);
				}
			}
			$stacks[$stackIndex]->setCards($cards);
		}
		return $stacks;
	}

	/**
	 * @param integer $order
	 */
	public function create($title, $boardId, $order) {
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		if ($this->boardService->isArchived(null, $boardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$stack = new Stack();
		$stack->setTitle($title);
		$stack->setBoardId($boardId);
		$stack->setOrder($order);
		return $this->stackMapper->insert($stack);

	}

	public function delete($id) {
		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_MANAGE);

		$stack = $this->stackMapper->find($id);
		$stack->setDeletedAt(time());
		$this->stackMapper->update($stack);

		return $stack;
	}


	public function update($id, $title, $boardId, $order, $deletedAt) {
		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_MANAGE);
		if ($this->boardService->isArchived($this->stackMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$stack = $this->stackMapper->find($id);
		$stack->setTitle($title);
		$stack->setBoardId($boardId);
		$stack->setOrder($order);
		$stack->setDeletedAt($deletedAt);
		return $this->stackMapper->update($stack);
	}

	public function reorder($id, $order) {
		$this->permissionService->checkPermission($this->stackMapper, $id, Acl::PERMISSION_EDIT);
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

		return $result;
	}
}
