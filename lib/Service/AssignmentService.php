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
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\IEventDispatcher;

class AssignmentService {

	/**
	 * @var PermissionService
	 */
	private $permissionService;
	/**
	 * @var CardMapper
	 */
	private $cardMapper;
	/**
	 * @var AssignmentMapper
	 */
	private $assignedUsersMapper;
	/**
	 * @var AclMapper
	 */
	private $aclMapper;
	/**
	 * @var NotificationHelper
	 */
	private $notificationHelper;
	/**
	 * @var ChangeHelper
	 */
	private $changeHelper;
	/**
	 * @var ActivityManager
	 */
	private $activityManager;
	/**
	 * @var IEventDispatcher
	 */
	private $eventDispatcher;
	/** @var string|null */
	private $currentUser;
	/**
	 * @var AssignmentServiceValidator
	 */
	private $assignmentServiceValidator;


	public function __construct(
		PermissionService $permissionService,
		CardMapper $cardMapper,
		AssignmentMapper $assignedUsersMapper,
		AclMapper $aclMapper,
		NotificationHelper $notificationHelper,
		ActivityManager $activityManager,
		ChangeHelper $changeHelper,
		IEventDispatcher $eventDispatcher,
		AssignmentServiceValidator $assignmentServiceValidator,
		$userId,
	) {
		$this->assignmentServiceValidator = $assignmentServiceValidator;
		$this->permissionService = $permissionService;
		$this->cardMapper = $cardMapper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->aclMapper = $aclMapper;
		$this->notificationHelper = $notificationHelper;
		$this->changeHelper = $changeHelper;
		$this->activityManager = $activityManager;
		$this->eventDispatcher = $eventDispatcher;
		$this->currentUser = $userId;
	}

	/**
	 * @param $cardId
	 * @param $userId
	 * @return bool|null|Entity
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function assignUser($cardId, $userId, int $type = Assignment::TYPE_USER) {
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
		if (!in_array($userId, $boardUsers) && count($groups) !== 1) {
			throw new BadRequestException('The user is not part of the board');
		}


		if ($type === Assignment::TYPE_USER && $userId !== $this->currentUser) {
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
	 * @param $cardId
	 * @param $userId
	 * @return Entity
	 * @throws BadRequestException
	 * @throws NotFoundException
	 * @throws NoPermissionException
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function unassignUser($cardId, $userId, $type = 0) {
		$this->assignmentServiceValidator->check(compact('cardId', 'userId'));
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);

		$assignments = $this->assignedUsersMapper->findAll($cardId);
		foreach ($assignments as $assignment) {
			if ($assignment->getParticipant() === $userId && $assignment->getType() === $type) {
				$assignment = $this->assignedUsersMapper->delete($assignment);
				$card = $this->cardMapper->find($cardId);
				$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_USER_UNASSIGN, ['assigneduser' => $userId]);
				if ($type === Assignment::TYPE_USER && $userId !== $this->currentUser) {
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
