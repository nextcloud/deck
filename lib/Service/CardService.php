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

use OCA\Deck\Db\AssignedUsers;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\NotFoundException;
use OCA\Deck\StatusException;


class CardService {

	private $cardMapper;
	private $stackMapper;
	private $boardMapper;
	private $permissionService;
	private $boardService;
	private $assignedUsersMapper;
	private $attachmentService;

	public function __construct(
		CardMapper $cardMapper,
		StackMapper $stackMapper, 
		BoardMapper $boardMapper, 
		PermissionService $permissionService, 
		BoardService $boardService, 
		AssignedUsersMapper $assignedUsersMapper, 
		AttachmentService $attachmentService) {

		$this->cardMapper = $cardMapper;
		$this->stackMapper = $stackMapper;
		$this->boardMapper = $boardMapper;
		$this->permissionService = $permissionService;
		$this->boardService = $boardService;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->attachmentService = $attachmentService;
	}

	public function fetchDeleted($boardId) {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		return $this->cardMapper->findDeleted($boardId);
	}

	public function find($cardId) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		$card = $this->cardMapper->find($cardId);
		$assignedUsers = $this->assignedUsersMapper->find($card->getId());
		$attachments = $this->attachmentService->findAll($cardId, true);
		$card->setAssignedUsers($assignedUsers);
		$card->setAttachments($attachments);
		return $card;
	}

	/**
	 * @param integer $order
	 */
	public function create($title, $stackId, $type, $order, $owner) {
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->stackMapper, $stackId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = new Card();
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType($type);
		$card->setOrder($order);
		$card->setOwner($owner);
		return $this->cardMapper->insert($card);

	}

	public function delete($id) {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setDeletedAt(time());
		$this->cardMapper->update($card);
		return $card;
	}

	public function update($id, $title, $stackId, $type, $order, $description, $owner, $duedate, $deletedAt) {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType($type);
		$card->setOrder($order);
		$card->setOwner($owner);
		$card->setDescription($description);
		$card->setDuedate($duedate);
		$card->setDeletedAt($deletedAt);
		return $this->cardMapper->update($card);
	}

	public function rename($id, $title) {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$card->setTitle($title);
		return $this->cardMapper->update($card);
	}

	public function reorder($id, $stackId, $order) {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$cards = $this->cardMapper->findAll($stackId);
		$result = [];
		$i = 0;
		foreach ($cards as $card) {
			if ($card->getArchived()) {
				throw new StatusException('Operation not allowed. This card is archived.');
			}
			if ($card->id === $id) {
				$card->setOrder($order);
				$card->setLastModified(time());
			}

			if ($i === $order) {
				$i++;
			}

			if ($card->id !== $id) {
				$card->setOrder($i++);
			}
			$this->cardMapper->update($card);
			$result[$card->getOrder()] = $card;
		}

		return $result;
	}

	public function archive($id) {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setArchived(true);
		return $this->cardMapper->update($card);
	}

	public function unarchive($id) {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setArchived(false);
		return $this->cardMapper->update($card);
	}

	public function assignLabel($cardId, $labelId) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$this->cardMapper->assignLabel($cardId, $labelId);
	}

	public function removeLabel($cardId, $labelId) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$this->cardMapper->removeLabel($cardId, $labelId);
	}

	public function assignUser($cardId, $userId) {
		$assignments = $this->assignedUsersMapper->find($cardId);
		foreach ($assignments as $assignment) {
			if ($assignment->getParticipant() === $userId) {
				return false;
			}
		}
		$assignment = new AssignedUsers();
		$assignment->setCardId($cardId);
		$assignment->setParticipant($userId);
		return $this->assignedUsersMapper->insert($assignment);
	}

	public function unassignUser($cardId, $userId) {
		$assignments = $this->assignedUsersMapper->find($cardId);
		foreach ($assignments as $assignment) {
			if ($assignment->getParticipant() === $userId) {
				return $this->assignedUsersMapper->delete($assignment);
			}
		}
		throw new NotFoundException('No assignment for ' . $userId . 'found.');
	}
}
