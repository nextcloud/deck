<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Activity;

use OCA\Deck\Db\AssignedUsers;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\PermissionService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use OCP\IUser;

class ActivityManager {

	private $manager;
	private $userId;
	private $permissionService;
	private $boardMapper;
	private $cardMapper;
	private $attachmentMapper;
	private $stackMapper;
	private $l10n;

	const DECK_OBJECT_BOARD = 'deck_board';
	const DECK_OBJECT_CARD = 'deck_card';

	const SUBJECT_BOARD_CREATE = 'board_create';
	const SUBJECT_BOARD_UPDATE = 'board_update';
	const SUBJECT_BOARD_UPDATE_TITLE = 'board_update_title';
	const SUBJECT_BOARD_UPDATE_ARCHIVED = 'board_update_archived';
	const SUBJECT_BOARD_DELETE = 'board_delete';
	const SUBJECT_BOARD_RESTORE = 'board_restore';
	const SUBJECT_BOARD_SHARE = 'board_share';
	const SUBJECT_BOARD_ARCHIVE = 'board_archive';
	const SUBJECT_BOARD_UNARCHIVE = 'board_unarchive';

	const SUBJECT_STACK_CREATE = 'stack_create';
	const SUBJECT_STACK_UPDATE = 'stack_update';
	const SUBJECT_STACK_UPDATE_TITLE = 'stack_update_title';
	const SUBJECT_STACK_UPDATE_ORDER = 'stack_update_order';
	const SUBJECT_STACK_DELETE = 'stack_delete';

	const SUBJECT_CARD_CREATE = 'card_create';
	const SUBJECT_CARD_DELETE = 'card_delete';
	const SUBJECT_CARD_RESTORE = 'card_restore';
	const SUBJECT_CARD_UPDATE = 'card_update';
	const SUBJECT_CARD_UPDATE_TITLE = 'card_update_title';
	const SUBJECT_CARD_UPDATE_DESCRIPTION = 'card_update_description';
	const SUBJECT_CARD_UPDATE_DUEDATE = 'card_update_duedate';
	const SUBJECT_CARD_UPDATE_ARCHIVE = 'card_update_archive';
	const SUBJECT_CARD_UPDATE_UNARCHIVE = 'card_update_unarchive';
	const SUBJECT_CARD_UPDATE_STACKID = 'card_update_stackId';
	const SUBJECT_CARD_USER_ASSIGN = 'card_user_assign';
	const SUBJECT_CARD_USER_UNASSIGN = 'card_user_unassign';
	const SUBJECT_CARD_MOVE_STACK = 'card_move_stack';


	const SUBJECT_ATTACHMENT_CREATE = 'attachment_create';
	const SUBJECT_ATTACHMENT_UPDATE = 'attachment_update';
	const SUBJECT_ATTACHMENT_DELETE = 'attachment_delete';
	const SUBJECT_ATTACHMENT_RESTORE = 'attachment_restore';

	const SUBJECT_LABEL_CREATE = 'label_create';
	const SUBJECT_LABEL_UPDATE = 'label_update';
	const SUBJECT_LABEL_DELETE = 'label_delete';
	const SUBJECT_LABEL_ASSIGN = 'label_assign';
	const SUBJECT_LABEL_UNASSING = 'label_unassign';

	public function __construct(
		IManager $manager,
		PermissionService $permissionsService,
		BoardMapper $boardMapper,
		CardMapper $cardMapper,
		StackMapper $stackMapper,
		AttachmentMapper $attachmentMapper,
		IL10N $l10n,
		$userId
	) {
		$this->manager = $manager;
		$this->permissionService = $permissionsService;
		$this->boardMapper = $boardMapper;
		$this->cardMapper = $cardMapper;
		$this->stackMapper = $stackMapper;
		$this->attachmentMapper = $attachmentMapper;
		$this->l10n = $l10n;
		$this->userId = $userId;
	}

	/**
	 * @param $subjectIdentifier
	 * @param array $subjectParams
	 * @param bool $ownActivity
	 * @return string
	 */
	public function getActivityFormat($subjectIdentifier, $subjectParams = [], $ownActivity = false) {
		$subject = '';
		switch ($subjectIdentifier) {
			case self::SUBJECT_BOARD_CREATE:
				$subject = $this->l10n->t('You have created a new board {board}');
				break;
			case self::SUBJECT_BOARD_DELETE:
				$subject = $ownActivity ? $this->l10n->t('You have deleted the board {board}') : $this->l10n->t('{user} has deleted the board {board}');
				break;
			case self::SUBJECT_BOARD_RESTORE:
				$subject = $ownActivity ? $this->l10n->t('You have restored the board {board}') : $this->l10n->t('{user} has restored the board {board}');
				break;
			case self::SUBJECT_BOARD_SHARE:
				$subject = $ownActivity ? $this->l10n->t('You have shared the board {board} with {sharee}') : $this->l10n->t('{user} has shared the board {board} with {sharee}');
				break;
			case self::SUBJECT_BOARD_ARCHIVE:
				$subject = $ownActivity ? $this->l10n->t('You have archived the board {board}') : $this->l10n->t('{user} has archived the board {board}');
				break;
			case self::SUBJECT_BOARD_UNARCHIVE:
				$subject = $ownActivity ? $this->l10n->t('You have unarchived the board {board}') : $this->l10n->t('{user} has unarchived the board {board}');
				break;

			case self::SUBJECT_BOARD_UPDATE_TITLE:
				$subject = $ownActivity ? $this->l10n->t('You have renamed the board {before} to {board}') : $this->l10n->t('{user} has has renamed the board {before} to {board}');
				break;
			case self::SUBJECT_BOARD_UPDATE_ARCHIVED:
				$subject = $ownActivity ? $this->l10n->t('You have renamed the board {before} to {board}') : $this->l10n->t('{user} has has renamed the board {before} to {board}');
				break;

			case self::SUBJECT_STACK_CREATE:
				$subject = $ownActivity ? $this->l10n->t('You have created a new stack {stack} on {board}') : $this->l10n->t('{user} has created a new stack {stack} on {board}');
				break;
			case self::SUBJECT_STACK_UPDATE:
				$subject = $ownActivity ? $this->l10n->t('You have created a new stack {stack} on {board}') : $this->l10n->t('{user} has created a new stack {stack} on {board}');
				break;
			case self::SUBJECT_STACK_UPDATE_TITLE:
				$subject = $ownActivity ? $this->l10n->t('You have renamed a new stack {before} to {stack} on {board}') : $this->l10n->t('{user} has renamed a new stack {before} to {stack} on {board}');
				break;
			case self::SUBJECT_STACK_DELETE:
				$subject = $ownActivity ? $this->l10n->t('You have deleted {stack} on {board}') : $this->l10n->t('{user} has deleted {stack} on {board}');
				break;
			case self::SUBJECT_CARD_CREATE:
				$subject = $ownActivity ? $this->l10n->t('You have created {card} in {stack} on {board}') : $this->l10n->t('{user} has created {card} in {stack} on {board}');
				break;
			case self::SUBJECT_CARD_DELETE:
				$subject = $ownActivity ? $this->l10n->t('You have deleted {card} in {stack} on {board}') : $this->l10n->t('{user} has deleted {card} in {stack} on {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_TITLE:
				$subject = $ownActivity ? $this->l10n->t('You have renamed the card {before} to {card}') : $this->l10n->t('{user} has renamed the card {before} to {card}');
				break;
			case self::SUBJECT_CARD_UPDATE_DESCRIPTION:
				if ($subjectParams['before'] === null) {
					$subject = $ownActivity ? $this->l10n->t('You have added a description to {card} in {stack} on {board}') : $this->l10n->t('{user} has added a description to {card} in {stack} on {board}');
				} else {
					$subject = $ownActivity ? $this->l10n->t('You have updated the description of {card} in {stack} on {board}') : $this->l10n->t('{user} has updated the description {card} in {stack} on {board}');
				}
				break;
			case self::SUBJECT_CARD_UPDATE_ARCHIVE:
				$subject = $ownActivity ? $this->l10n->t('You have archived {card} in {stack} on {board}') : $this->l10n->t('{user} has archived {card} in {stack} on {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_UNARCHIVE:
				$subject = $ownActivity ? $this->l10n->t('You have unarchived {card} in {stack} on {board}') : $this->l10n->t('{user} has unarchived {card} in {stack} on {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_DUEDATE:
				if ($subjectParams['after'] === null) {
					$subject = $ownActivity ? $this->l10n->t('You have removed the due date of {card}') : $this->l10n->t('{user} has removed the due date of {card}');
				} else if ($subjectParams['before'] === null && $subjectParams['after'] !== null) {
					$subject = $ownActivity ? $this->l10n->t('You have set the due date of {card} to {after}') : $this->l10n->t('{user} has set the due date of {card} to {after}');
				} else {
					$subject = $ownActivity ? $this->l10n->t('You have updated the due date of {card} to {after}') : $this->l10n->t('{user} has updated the due date of {card} to {after}');
				}

				break;
			case self::SUBJECT_LABEL_ASSIGN:
				$subject = $ownActivity ? $this->l10n->t('You have added the label {label} to {card} in {stack} on {board}') : $this->l10n->t('{user} has added the label {label} to {card} in {stack} on {board}');
				break;
			case self::SUBJECT_LABEL_UNASSING:
				$subject = $ownActivity ? $this->l10n->t('You have removed the label {label} from {card} in {stack} on {board}') : $this->l10n->t('{user} has removed the label {label} from {card} in {stack} on {board}');
				break;
			case self::SUBJECT_CARD_USER_ASSIGN:
				$subject = $ownActivity ? $this->l10n->t('You have assigned {assigneduser} to {card} on {board}') : $this->l10n->t('{user} has assigned {assigneduser} to {card} on {board}');
				break;
			case self::SUBJECT_CARD_USER_UNASSIGN:
				$subject = $ownActivity ? $this->l10n->t('You have unassigned {assigneduser} from {card} on {board}') : $this->l10n->t('{user} has unassigned {assigneduser} from {card} on {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_STACKID:
				$subject = $ownActivity ? $this->l10n->t('You have moved the card {card} from {before} to {stack}') : $this->l10n->t('{user} has moved the card {card} from {before} to {stack}');
				break;
			case self::SUBJECT_ATTACHMENT_CREATE:
				$subject = $ownActivity ? $this->l10n->t('You have added the attachment {attachment} to {card}') : $this->l10n->t('{user} has added the attachment {attachment} to {card}');
				break;
			case self::SUBJECT_ATTACHMENT_UPDATE:
				$subject = $ownActivity ? $this->l10n->t('You have updated the attachment {attachment} on {card}') : $this->l10n->t('{user} has updated the attachment {attachment} to {card}');
				break;
			case self::SUBJECT_ATTACHMENT_DELETE:
				$subject = $ownActivity ? $this->l10n->t('You have deleted the attachment {attachment} from {card}') : $this->l10n->t('{user} has deleted the attachment {attachment} to {card}');
				break;
			case self::SUBJECT_ATTACHMENT_RESTORE:
				$subject = $ownActivity ? $this->l10n->t('You have restored the attachment {attachment} to {card}') : $this->l10n->t('{user} has restored the attachment {attachment} to {card}');
				break;
			default:
				break;
		}
		return $subject;
	}

	public function triggerEvent($objectType, $entity, $subject, $additionalParams = []) {
		try {
			$event = $this->createEvent($objectType, $entity, $subject, $additionalParams);
			$this->sendToUsers($event);
		} catch (\Exception $e) {
			// Ignore exception for undefined activities on update events
		}
	}

	/**
	 *
	 * @param $objectType
	 * @param ChangeSet $changeSet
	 * @param $subject
	 * @throws \Exception
	 */
	public function triggerUpdateEvents($objectType, ChangeSet $changeSet, $subject) {
		$previousEntity = $changeSet->getBefore();
		$entity = $changeSet->getAfter();
		$events = [];
		if ($previousEntity !== null) {
			foreach ($entity->getUpdatedFields() as $field => $value) {
				$getter = 'get' . ucfirst($field);
				$subject = $subject . '_' . $field;
				$changes = [
					'before' => $previousEntity->$getter(),
					'after' => $entity->$getter()
				];
				if ($changes['before'] !== $changes['after']) {
					try {
						$event = $this->createEvent($objectType, $entity, $subject, $changes);
						$events[] = $event;
					} catch (\Exception $e) {
						// Ignore exception for undefined activities on update events
					}
				}
			}

		} else {
			try {
				$events = [$this->createEvent($objectType, $entity, $subject)];
			} catch (\Exception $e) {
				// Ignore exception for undefined activities on update events
			}
		}
		foreach ($events as $event) {
			$this->sendToUsers($event);
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @param $subject
	 * @param array $additionalParams
	 * @return IEvent
	 * @throws \Exception
	 */
	private function createEvent($objectType, $entity, $subject, $additionalParams = []) {
		try {
			$object = $this->findObjectForEntity($objectType, $entity);
		} catch (DoesNotExistException $e) {
		} catch (MultipleObjectsReturnedException $e) {
			\OC::$server->getLogger()->error('Could not create activity entry for ' . $subject . '. Entity not found.', $entity);
			return null;
		}

		/**
		 * Automatically fetch related details for subject parameters
		 * depending on the subject
		 */
		$subjectParams = [];
		$message = null;
		switch ($subject) {
			// No need to enhance parameters since entity already contains the required data
			case self::SUBJECT_BOARD_CREATE:
			case self::SUBJECT_BOARD_UPDATE_TITLE:
			case self::SUBJECT_BOARD_UPDATE_ARCHIVED:
			// Not defined as there is no activity for
			// case self::SUBJECT_BOARD_UPDATE_COLOR
				break;

			case self::SUBJECT_STACK_CREATE:
			case self::SUBJECT_STACK_UPDATE:
			case self::SUBJECT_STACK_UPDATE_TITLE:
			case self::SUBJECT_STACK_UPDATE_ORDER:
			case self::SUBJECT_STACK_DELETE:
				$subjectParams = $this->findDetailsForStack($entity->getId());
				break;

			case self::SUBJECT_CARD_CREATE:
			case self::SUBJECT_CARD_DELETE:
			case self::SUBJECT_CARD_UPDATE_ARCHIVE:
			case self::SUBJECT_CARD_UPDATE_UNARCHIVE:
			case self::SUBJECT_CARD_UPDATE_TITLE:
			case self::SUBJECT_CARD_UPDATE_DESCRIPTION:
			case self::SUBJECT_CARD_UPDATE_DUEDATE:
			case self::SUBJECT_CARD_UPDATE_STACKID:
			case self::SUBJECT_LABEL_ASSIGN:
			case self::SUBJECT_LABEL_UNASSING:
			case self::SUBJECT_CARD_USER_ASSIGN:
			case self::SUBJECT_CARD_USER_UNASSIGN:
			case self::SUBJECT_CARD_MOVE_STACK:
				$subjectParams = $this->findDetailsForCard($entity->getId());
				$object = $entity;
				break;
			case self::SUBJECT_ATTACHMENT_CREATE:
			case self::SUBJECT_ATTACHMENT_UPDATE:
			case self::SUBJECT_ATTACHMENT_DELETE:
			case self::SUBJECT_ATTACHMENT_RESTORE:
				$subjectParams = $this->findDetailsForAttachment($entity->getId());
				$object = $subjectParams['card'];
				break;
			default:
				throw new \Exception('Unknown subject for activity.');
				break;
		}

		if ($subject === self::SUBJECT_CARD_UPDATE_DESCRIPTION){
			$message = $additionalParams['after'];
		}

		$event = $this->manager->generateEvent();
		$event->setApp('deck')
			->setType('deck')
			->setAuthor($this->userId)
			->setObject($objectType, (int)$object->getId(), $object->getTitle())
			->setSubject($subject, array_merge($subjectParams, $additionalParams))
			->setTimestamp(time());

		if ($message !== null) {
			$event->setMessage($message);
		}

		return $event;
	}

	/**
	 * Publish activity to all users that are part of the board of a given object
	 *
	 * @param IEvent $event
	 */
	private function sendToUsers(IEvent $event) {
		switch ($event->getObjectType()) {
			case self::DECK_OBJECT_BOARD:
				$mapper = $this->boardMapper;
				break;
			case self::DECK_OBJECT_CARD:
				$mapper = $this->cardMapper;
				break;
		}
		$boardId = $mapper->findBoardId($event->getObjectId());
		/** @var IUser $user */
		foreach ($this->permissionService->findUsers($boardId) as $user) {
			$event->setAffectedUser($user->getUID());
			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @return null|\OCA\Deck\Db\RelationalEntity|\OCP\AppFramework\Db\Entity
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	private function findObjectForEntity($objectType, $entity) {
		$className = \get_class($entity);
		$objectId = null;
		switch ($className) {
			case Board::class:
			case Card::class:
				$objectId = $entity->getId();
				break;
			case Attachment::class:
			case Label::class:
			case AssignedUsers::class:
				$objectId = $entity->getCardId();
				break;
			case Stack::class:
				$objectId = $entity->getBoardId();
		}

		if ($objectType === self::DECK_OBJECT_CARD) {
			return $this->cardMapper->find($objectId);
		}
		if ($objectType === self::DECK_OBJECT_BOARD) {
			return $this->boardMapper->find($objectId);
		}

		return null;
	}

	private function findDetailsForStack($stackId) {
		$stack = $this->stackMapper->find($stackId);
		$board = $this->boardMapper->find($stack->getBoardId());
		return [
			'stack' => $stack,
			'board' => $board
		];
	}

	private function findDetailsForCard($cardId) {
		$card = $this->cardMapper->find($cardId);
		$stack = $this->stackMapper->find($card->getStackId());
		$board = $this->boardMapper->find($stack->getBoardId());
		return [
			'card' => $card,
			'stack' => $stack,
			'board' => $board
		];
	}

	private function findDetailsForAttachment($attachmentId) {
		$attachment = $this->attachmentMapper->find($attachmentId);
		$data = $this->findDetailsForCard($attachment->getCardId());
		return array_merge($data, ['attachment' => $attachment]);
	}

}
