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
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\NotFoundException;
use OCA\Deck\StatusException;
use OCA\Deck\BadRequestException;

class CardService {

	private $cardMapper;
	private $stackMapper;
	private $boardMapper;
	private $labelMapper;
	private $permissionService;
	private $boardService;
	private $notificationHelper;
	private $assignedUsersMapper;
	private $attachmentService;
	private $currentUser;

	public function __construct(
		CardMapper $cardMapper,
		StackMapper $stackMapper,
		BoardMapper $boardMapper,
		LabelMapper $labelMapper,
		PermissionService $permissionService, 
		BoardService $boardService,
		NotificationHelper $notificationHelper,
		AssignedUsersMapper $assignedUsersMapper, 
		AttachmentService $attachmentService,
		$userId
	) {
		$this->cardMapper = $cardMapper;
		$this->stackMapper = $stackMapper;
		$this->boardMapper = $boardMapper;
		$this->labelMapper = $labelMapper;
		$this->permissionService = $permissionService;
		$this->boardService = $boardService;
		$this->notificationHelper = $notificationHelper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->attachmentService = $attachmentService;
		$this->currentUser = $userId;
	}

	public function enrich($card) {
		$cardId = $card->getId();
		$card->setAssignedUsers($this->assignedUsersMapper->find($cardId));
		$card->setLabels($this->labelMapper->findAssignedLabelsForCard($cardId));
		$card->setAttachmentCount($this->attachmentService->count($cardId));
	}

	public function fetchDeleted($boardId) {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		$cards = $this->cardMapper->findDeleted($boardId);
		foreach ($cards as $card) {
			$this->enrich($card);
		}
		return $cards;
	}

	/**
	 * @param $cardId
	 * @return \OCA\Deck\Db\RelationalEntity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
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
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param integer $order
	 * @param $owner
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadrequestException
	 */
	public function create($title, $stackId, $type, $order, $owner) {

		if ($title === 'false' || $title === null) {
			throw new BadRequestException('title must be provided');
		}

		if (is_numeric($stackId) === false) {
			throw new BadRequestException('stack id must be a number');
		}

		if ($type === 'false' || $type === null) {
			throw new BadRequestException('type must be provided');
		}

		if (is_numeric($order) === false) {
			throw new BadRequestException('order must be a number');
		}

		if ($owner === false || $owner === null) {
			throw new BadRequestException('owner must be provided');
		}

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

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete($id) {

		if (is_numeric($id) === false) {
			throw new BadRequestException('card id must be a number');
		}

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setDeletedAt(time());
		$this->cardMapper->update($card);
		return $card;
	}

	/**
	 * @param $id
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param $order
	 * @param $description
	 * @param $owner
	 * @param $duedate
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update($id, $title, $stackId, $type, $order = 0, $description = '', $owner, $duedate = null, $deletedAt) {

		if (is_numeric($id) === false) {
			throw new BadRequestException('card id must be a number');			
		}

		if ($title === false || $title === null) {
			throw new BadRequestException('title must be provided');
		}

		if (is_numeric($stackId) === false) {
			throw new BadRequestException('stack id must be a number $$$');
		}

		if ($type === false || $type === null) {
			throw new BadRequestException('type must be provided');
		}

		if ($owner === false || $owner === null) {
			throw new BadRequestException('owner must be provided');
		}

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

	/**
	 * @param $id
	 * @param $title
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
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

	/**
	 * @param $id
	 * @param $stackId
	 * @param $order
	 * @return array
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function reorder($id, $stackId, $order) {

		if (is_numeric($id) === false) {
			throw new BadRequestException('card id must be a number');
		}

		if (is_numeric($stackId) === false) {
			throw new BadRequestException('stack id must be a number');
		}

		if (is_numeric($order) === false) {
			throw new BadRequestException('order must be a number');
		}

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

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function archive($id) {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setArchived(true);
		return $this->cardMapper->update($card);
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function unarchive($id) {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setArchived(false);
		return $this->cardMapper->update($card);
	}

	/**
	 * @param $cardId
	 * @param $labelId
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function assignLabel($cardId, $labelId) {

		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		if (is_numeric($labelId) === false) {
			throw new BadRequestException('label id must be a number');
		}

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

	/**
	 * @param $cardId
	 * @param $labelId
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function removeLabel($cardId, $labelId) {

		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		if (is_numeric($labelId) === false) {
			throw new BadRequestException('label id must be a number');
		}

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

	/**
	 * @param $cardId
	 * @param $userId
	 * @return bool|null|\OCP\AppFramework\Db\Entity
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function assignUser($cardId, $userId) {

		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		if ($userId === false || $userId === null) {
			throw new BadRequestException('user id must be provided');
		}

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$assignments = $this->assignedUsersMapper->find($cardId);
		foreach ($assignments as $assignment) {
			if ($assignment->getParticipant() === $userId) {
				return false;
			}
		}

		if ($userId !== $this->currentUser) {
			/* Notifyuser about the card assignment */
			$card = $this->cardMapper->find($cardId);
			$this->notificationHelper->sendCardAssigned($card, $userId);
		}

		$assignment = new AssignedUsers();
		$assignment->setCardId($cardId);
		$assignment->setParticipant($userId);
		return $this->assignedUsersMapper->insert($assignment);
	}

	/**
	 * @param $cardId
	 * @param $userId
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws NotFoundException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function unassignUser($cardId, $userId) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);

		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		if ($userId === false || $userId === null) {
			throw new BadRequestException('user must be provided');
		}

		$assignments = $this->assignedUsersMapper->find($cardId);
		foreach ($assignments as $assignment) {
			if ($assignment->getParticipant() === $userId) {
				return $this->assignedUsersMapper->delete($assignment);
			}
		}
		throw new NotFoundException('No assignment for ' . $userId . 'found.');
	}
}
