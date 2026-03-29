<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\Validators\AssignmentServiceValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\IEventDispatcher;

class AssignmentService {
	public function __construct(
		private readonly PermissionService $permissionService,
		private readonly CardMapper $cardMapper,
		private readonly AssignmentMapper $assignedUsersMapper,
		private readonly AclMapper $aclMapper,
		private readonly NotificationHelper $notificationHelper,
		private readonly ActivityManager $activityManager,
		private readonly ChangeHelper $changeHelper,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly AssignmentServiceValidator $assignmentServiceValidator,
		private readonly ?string $userId,
	) {
	}

	/**
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function assignUser(int $cardId, string $userId, int $type = Assignment::TYPE_USER): Assignment {
		$this->assignmentServiceValidator->check(compact('cardId', 'userId'));

		if ($type !== Assignment::TYPE_USER && $type !== Assignment::TYPE_GROUP) {
			throw new BadRequestException('Invalid type provided for assignemnt');
		}

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$assignments = $this->assignedUsersMapper->findAll($cardId);
		foreach ($assignments as $assignment) {
			if ($assignment->getParticipant() === $userId && $assignment->getType() === $type) {
				throw new BadRequestException('The user is already assigned to the card');
			}
		}

		$card = $this->cardMapper->find($cardId);
		$boardId = $this->cardMapper->findBoardId($cardId);
		$boardUsers = array_keys($this->permissionService->findUsers($boardId, true));
		$groups = array_filter($this->aclMapper->findAll($boardId), function (Acl $acl) use ($userId) {
			return $acl->getType() === Acl::PERMISSION_TYPE_GROUP && $acl->getParticipant() === $userId;
		});
		if (!in_array($userId, $boardUsers, true) && count($groups) !== 1) {
			throw new BadRequestException('The user is not part of the board');
		}


		if ($type === Assignment::TYPE_USER && $userId !== $this->userId) {
			$this->notificationHelper->sendCardAssigned($card, $userId);
		}

		$assignment = new Assignment();
		$assignment->setCardId($cardId);
		$assignment->setParticipant($userId);
		$assignment->setType($type);
		$assignment = $this->assignedUsersMapper->insert($assignment);
		$this->changeHelper->cardChanged($cardId);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_USER_ASSIGN, ['assigneduser' => $userId]);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $assignment;
	}

	/**
	 * @throws BadRequestException
	 * @throws NotFoundException
	 * @throws NoPermissionException
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function unassignUser(int $cardId, string $userId, int $type = 0): Assignment {
		$this->assignmentServiceValidator->check(compact('cardId', 'userId'));
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);

		$assignments = $this->assignedUsersMapper->findAll($cardId);
		foreach ($assignments as $assignment) {
			if ($assignment->getParticipant() === $userId && $assignment->getType() === $type) {
				$assignment = $this->assignedUsersMapper->delete($assignment);
				$card = $this->cardMapper->find($cardId);
				$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_USER_UNASSIGN, ['assigneduser' => $userId]);
				if ($type === Assignment::TYPE_USER && $userId !== $this->userId) {
					$this->notificationHelper->markCardAssignedAsRead($card, $userId);
				}
				$this->changeHelper->cardChanged($cardId);


				$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

				return $assignment;
			}
		}
		throw new NotFoundException('No assignment for ' . $userId . 'found.');
	}
}
