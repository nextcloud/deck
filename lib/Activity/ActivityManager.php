<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Activity;

use InvalidArgumentException;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\PermissionService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Comments\IComment;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Server;
use Psr\Log\LoggerInterface;

class ActivityManager {
	public const DECK_NOAUTHOR_COMMENT_SYSTEM_ENFORCED = 'DECK_NOAUTHOR_COMMENT_SYSTEM_ENFORCED';

	public const SUBJECT_PARAMS_MAX_LENGTH = 4000;
	public const SHORTENED_DESCRIPTION_MAX_LENGTH = 2000;

	private IManager $manager;
	private ?string $userId;
	private PermissionService $permissionService;
	private BoardMapper $boardMapper;
	private CardMapper $cardMapper;
	private AclMapper $aclMapper;
	private StackMapper $stackMapper;
	private IFactory $l10nFactory;

	public const DECK_OBJECT_BOARD = 'deck_board';
	public const DECK_OBJECT_CARD = 'deck_card';

	public const SUBJECT_BOARD_CREATE = 'board_create';
	public const SUBJECT_BOARD_UPDATE = 'board_update';
	public const SUBJECT_BOARD_UPDATE_TITLE = 'board_update_title';
	public const SUBJECT_BOARD_UPDATE_ARCHIVED = 'board_update_archived';
	public const SUBJECT_BOARD_DELETE = 'board_delete';
	public const SUBJECT_BOARD_RESTORE = 'board_restore';
	public const SUBJECT_BOARD_SHARE = 'board_share';
	public const SUBJECT_BOARD_UNSHARE = 'board_unshare';

	public const SUBJECT_STACK_CREATE = 'stack_create';
	public const SUBJECT_STACK_UPDATE = 'stack_update';
	public const SUBJECT_STACK_UPDATE_TITLE = 'stack_update_title';
	public const SUBJECT_STACK_UPDATE_ORDER = 'stack_update_order';
	public const SUBJECT_STACK_DELETE = 'stack_delete';

	public const SUBJECT_CARD_CREATE = 'card_create';
	public const SUBJECT_CARD_DELETE = 'card_delete';
	public const SUBJECT_CARD_RESTORE = 'card_restore';
	public const SUBJECT_CARD_UPDATE = 'card_update';
	public const SUBJECT_CARD_UPDATE_TITLE = 'card_update_title';
	public const SUBJECT_CARD_UPDATE_DESCRIPTION = 'card_update_description';
	public const SUBJECT_CARD_UPDATE_DUEDATE = 'card_update_duedate';
	public const SUBJECT_CARD_UPDATE_ARCHIVE = 'card_update_archive';
	public const SUBJECT_CARD_UPDATE_UNARCHIVE = 'card_update_unarchive';
	public const SUBJECT_CARD_UPDATE_DONE = 'card_update_done';
	public const SUBJECT_CARD_UPDATE_UNDONE = 'card_update_undone';
	public const SUBJECT_CARD_UPDATE_STACKID = 'card_update_stackId';
	public const SUBJECT_CARD_USER_ASSIGN = 'card_user_assign';
	public const SUBJECT_CARD_USER_UNASSIGN = 'card_user_unassign';

	public const SUBJECT_ATTACHMENT_CREATE = 'attachment_create';
	public const SUBJECT_ATTACHMENT_UPDATE = 'attachment_update';
	public const SUBJECT_ATTACHMENT_DELETE = 'attachment_delete';
	public const SUBJECT_ATTACHMENT_RESTORE = 'attachment_restore';

	public const SUBJECT_LABEL_CREATE = 'label_create';
	public const SUBJECT_LABEL_UPDATE = 'label_update';
	public const SUBJECT_LABEL_DELETE = 'label_delete';
	public const SUBJECT_LABEL_ASSIGN = 'label_assign';
	public const SUBJECT_LABEL_UNASSING = 'label_unassign';

	public const SUBJECT_CARD_COMMENT_CREATE = 'card_comment_create';

	public function __construct(
		IManager $manager,
		PermissionService $permissionsService,
		BoardMapper $boardMapper,
		CardMapper $cardMapper,
		StackMapper $stackMapper,
		AclMapper $aclMapper,
		IFactory $l10nFactory,
		?string $userId,
	) {
		$this->manager = $manager;
		$this->permissionService = $permissionsService;
		$this->boardMapper = $boardMapper;
		$this->cardMapper = $cardMapper;
		$this->stackMapper = $stackMapper;
		$this->aclMapper = $aclMapper;
		$this->l10nFactory = $l10nFactory;
		$this->userId = $userId;
	}

	/**
	 * @param string $subjectIdentifier
	 * @param array $subjectParams
	 * @param bool $ownActivity
	 * @return string
	 */
	public function getActivityFormat($language, $subjectIdentifier, $subjectParams = [], $ownActivity = false) {
		$subject = '';
		$l = $this->l10nFactory->get(Application::APP_ID, $language);

		switch ($subjectIdentifier) {
			case self::SUBJECT_BOARD_CREATE:
				$subject = $ownActivity ? $l->t('You have created a new board {board}'): $l->t('{user} has created a new board {board}');
				break;
			case self::SUBJECT_BOARD_DELETE:
				$subject = $ownActivity ? $l->t('You have deleted the board {board}') : $l->t('{user} has deleted the board {board}');
				break;
			case self::SUBJECT_BOARD_RESTORE:
				$subject = $ownActivity ? $l->t('You have restored the board {board}') : $l->t('{user} has restored the board {board}');
				break;
			case self::SUBJECT_BOARD_SHARE:
				$subject = $ownActivity ? $l->t('You have shared the board {board} with {acl}') : $l->t('{user} has shared the board {board} with {acl}');
				break;
			case self::SUBJECT_BOARD_UNSHARE:
				$subject = $ownActivity ? $l->t('You have removed {acl} from the board {board}') : $l->t('{user} has removed {acl} from the board {board}');
				break;
			case self::SUBJECT_BOARD_UPDATE_TITLE:
				$subject = $ownActivity ? $l->t('You have renamed the board {before} to {board}') : $l->t('{user} has renamed the board {before} to {board}');
				break;
			case self::SUBJECT_BOARD_UPDATE_ARCHIVED:
				if (isset($subjectParams['after']) && $subjectParams['after']) {
					$subject = $ownActivity ? $l->t('You have archived the board {board}') : $l->t('{user} has archived the board {before}');
				} else {
					$subject = $ownActivity ? $l->t('You have unarchived the board {board}') : $l->t('{user} has unarchived the board {before}');
				}
				break;
			case self::SUBJECT_STACK_CREATE:
				$subject = $ownActivity ? $l->t('You have created a new list {stack} on board {board}') : $l->t('{user} has created a new list {stack} on board {board}');
				break;
			case self::SUBJECT_STACK_UPDATE:
				$subject = $ownActivity ? $l->t('You have created a new list {stack} on board {board}') : $l->t('{user} has created a new list {stack} on board {board}');
				break;
			case self::SUBJECT_STACK_UPDATE_TITLE:
				$subject = $ownActivity ? $l->t('You have renamed list {before} to {stack} on board {board}') : $l->t('{user} has renamed list {before} to {stack} on board {board}');
				break;
			case self::SUBJECT_STACK_DELETE:
				$subject = $ownActivity ? $l->t('You have deleted list {stack} on board {board}') : $l->t('{user} has deleted list {stack} on board {board}');
				break;
			case self::SUBJECT_CARD_CREATE:
				$subject = $ownActivity ? $l->t('You have created card {card} in list {stack} on board {board}') : $l->t('{user} has created card {card} in list {stack} on board {board}');
				break;
			case self::SUBJECT_CARD_DELETE:
				$subject = $ownActivity ? $l->t('You have deleted card {card} in list {stack} on board {board}') : $l->t('{user} has deleted card {card} in list {stack} on board {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_TITLE:
				$subject = $ownActivity ? $l->t('You have renamed the card {before} to {card}') : $l->t('{user} has renamed the card {before} to {card}');
				break;
			case self::SUBJECT_CARD_UPDATE_DESCRIPTION:
				if (!isset($subjectParams['before'])) {
					$subject = $ownActivity ? $l->t('You have added a description to card {card} in list {stack} on board {board}') : $l->t('{user} has added a description to card {card} in list {stack} on board {board}');
				} else {
					$subject = $ownActivity ? $l->t('You have updated the description of card {card} in list {stack} on board {board}') : $l->t('{user} has updated the description of the card {card} in list {stack} on board {board}');
				}
				break;
			case self::SUBJECT_CARD_UPDATE_ARCHIVE:
				$subject = $ownActivity ? $l->t('You have archived card {card} in list {stack} on board {board}') : $l->t('{user} has archived card {card} in list {stack} on board {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_UNARCHIVE:
				$subject = $ownActivity ? $l->t('You have unarchived card {card} in list {stack} on board {board}') : $l->t('{user} has unarchived card {card} in list {stack} on board {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_DONE:
				$subject = $ownActivity ? $l->t('You have marked the card {card} as done in list {stack} on board {board}') : $l->t('{user} has marked card {card} as done in list {stack} on board {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_UNDONE:
				$subject = $ownActivity ? $l->t('You have marked the card {card} as undone in list {stack} on board {board}') : $l->t('{user} has marked the card {card} as undone in list {stack} on board {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_DUEDATE:
				if (!isset($subjectParams['after'])) {
					$subject = $ownActivity ? $l->t('You have removed the due date of card {card}') : $l->t('{user} has removed the due date of card {card}');
				} elseif (!isset($subjectParams['before']) && isset($subjectParams['after'])) {
					$subject = $ownActivity ? $l->t('You have set the due date of card {card} to {after}') : $l->t('{user} has set the due date of card {card} to {after}');
				} else {
					$subject = $ownActivity ? $l->t('You have updated the due date of card {card} to {after}') : $l->t('{user} has updated the due date of card {card} to {after}');
				}

				break;
			case self::SUBJECT_LABEL_ASSIGN:
				$subject = $ownActivity ? $l->t('You have added the tag {label} to card {card} in list {stack} on board {board}') : $l->t('{user} has added the tag {label} to card {card} in list {stack} on board {board}');
				break;
			case self::SUBJECT_LABEL_UNASSING:
				$subject = $ownActivity ? $l->t('You have removed the tag {label} from card {card} in list {stack} on board {board}') : $l->t('{user} has removed the tag {label} from card {card} in list {stack} on board {board}');
				break;
			case self::SUBJECT_CARD_USER_ASSIGN:
				$subject = $ownActivity ? $l->t('You have assigned {assigneduser} to card {card} on board {board}') : $l->t('{user} has assigned {assigneduser} to card {card} on board {board}');
				break;
			case self::SUBJECT_CARD_USER_UNASSIGN:
				$subject = $ownActivity ? $l->t('You have unassigned {assigneduser} from card {card} on board {board}') : $l->t('{user} has unassigned {assigneduser} from card {card} on board {board}');
				break;
			case self::SUBJECT_CARD_UPDATE_STACKID:
				$subject = $ownActivity ? $l->t('You have moved the card {card} from list {stackBefore} to {stack}') : $l->t('{user} has moved the card {card} from list {stackBefore} to {stack}');
				break;
			case self::SUBJECT_ATTACHMENT_CREATE:
				$subject = $ownActivity ? $l->t('You have added the attachment {attachment} to card {card}') : $l->t('{user} has added the attachment {attachment} to card {card}');
				break;
			case self::SUBJECT_ATTACHMENT_UPDATE:
				$subject = $ownActivity ? $l->t('You have updated the attachment {attachment} on card {card}') : $l->t('{user} has updated the attachment {attachment} on card {card}');
				break;
			case self::SUBJECT_ATTACHMENT_DELETE:
				$subject = $ownActivity ? $l->t('You have deleted the attachment {attachment} from card {card}') : $l->t('{user} has deleted the attachment {attachment} from card {card}');
				break;
			case self::SUBJECT_ATTACHMENT_RESTORE:
				$subject = $ownActivity ? $l->t('You have restored the attachment {attachment} to card {card}') : $l->t('{user} has restored the attachment {attachment} to card {card}');
				break;
			case self::SUBJECT_CARD_COMMENT_CREATE:
				$subject = $ownActivity ? $l->t('You have commented on card {card}') : $l->t('{user} has commented on card {card}');
				break;
			default:
				break;
		}
		return $subject;
	}

	public function triggerEvent($objectType, $entity, $subject, $additionalParams = [], $author = null) {
		if ($author === null) {
			$author = $this->userId;
		}
		try {
			$event = $this->createEvent($objectType, $entity, $subject, $additionalParams, $author);
			if ($event !== null) {
				$this->sendToUsers($event);
			}
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
				$subjectComplete = $subject . '_' . $field;
				$changes = [
					'before' => $previousEntity->$getter(),
					'after' => $entity->$getter()
				];
				if ($changes['before'] !== $changes['after']) {
					try {
						$event = $this->createEvent($objectType, $entity, $subjectComplete, $changes);
						if ($event !== null) {
							$events[] = $event;
						}
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
	 * @return IEvent|null
	 * @throws \Exception
	 */
	private function createEvent($objectType, $entity, $subject, $additionalParams = [], $author = null) {
		try {
			$object = $this->findObjectForEntity($objectType, $entity);
		} catch (DoesNotExistException $e) {
			Server::get(LoggerInterface::class)->error('Could not create activity entry for ' . $subject . '. Entity not found.', (array)$entity);
			return null;
		} catch (MultipleObjectsReturnedException $e) {
			Server::get(LoggerInterface::class)->error('Could not create activity entry for ' . $subject . '. Entity not found.', (array)$entity);
			return null;
		}

		/**
		 * Automatically fetch related details for subject parameters
		 * depending on the subject
		 */
		$eventType = 'deck';
		$subjectParams = [];
		switch ($subject) {
			// No need to enhance parameters since entity already contains the required data
			case self::SUBJECT_BOARD_CREATE:
			case self::SUBJECT_BOARD_UPDATE_TITLE:
			case self::SUBJECT_BOARD_UPDATE_ARCHIVED:
			case self::SUBJECT_BOARD_DELETE:
			case self::SUBJECT_BOARD_RESTORE:
				// Not defined as there is no activity for
				// case self::SUBJECT_BOARD_UPDATE_COLOR
				break;
			case self::SUBJECT_CARD_COMMENT_CREATE:
				$eventType = 'deck_comment';
				$subjectParams = $this->findDetailsForCard($entity->getId());
				if (array_key_exists('comment', $additionalParams)) {
					/** @var IComment $entity */
					$comment = $additionalParams['comment'];
					$subjectParams['comment'] = $comment->getId();
					unset($additionalParams['comment']);
				}
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
			case self::SUBJECT_CARD_UPDATE_DONE:
			case self::SUBJECT_CARD_UPDATE_UNDONE:
			case self::SUBJECT_CARD_UPDATE_TITLE:
			case self::SUBJECT_CARD_UPDATE_DESCRIPTION:
			case self::SUBJECT_CARD_UPDATE_DUEDATE:
			case self::SUBJECT_CARD_UPDATE_STACKID:
			case self::SUBJECT_LABEL_ASSIGN:
			case self::SUBJECT_LABEL_UNASSING:
			case self::SUBJECT_CARD_USER_ASSIGN:
			case self::SUBJECT_CARD_USER_UNASSIGN:
				$subjectParams = $this->findDetailsForCard($entity->getId(), $subject);

				if (isset($additionalParams['after']) && $additionalParams['after'] instanceof \DateTimeInterface) {
					$additionalParams['after'] = $additionalParams['after']->format('c');
				}
				if (isset($additionalParams['before']) && $additionalParams['before'] instanceof \DateTimeInterface) {
					$additionalParams['before'] = $additionalParams['before']->format('c');
				}

				break;
			case self::SUBJECT_ATTACHMENT_CREATE:
			case self::SUBJECT_ATTACHMENT_UPDATE:
			case self::SUBJECT_ATTACHMENT_DELETE:
			case self::SUBJECT_ATTACHMENT_RESTORE:
				$subjectParams = $this->findDetailsForAttachment($entity);
				break;
			case self::SUBJECT_BOARD_SHARE:
			case self::SUBJECT_BOARD_UNSHARE:
				$subjectParams = $this->findDetailsForAcl($entity->getId());
				break;
			default:
				throw new \Exception('Unknown subject for activity.');
				break;
		}

		if ($subject === self::SUBJECT_CARD_UPDATE_DESCRIPTION) {
			$card = $subjectParams['card'];
			if ($card->getLastEditor() === $this->userId) {
				return null;
			}
			$subjectParams['diff'] = true;
			$eventType = 'deck_card_description';
		}
		if ($subject === self::SUBJECT_CARD_UPDATE_STACKID) {
			$subjectParams['stackBefore'] = $this->stackMapper->find($additionalParams['before']);
			$subjectParams['stack'] = $this->stackMapper->find($additionalParams['after']);
			unset($additionalParams['after'], $additionalParams['before']);
		}

		$subjectParams['author'] = $author === null ? $this->userId : $author;

		$subjectParams = array_merge($subjectParams, $additionalParams);
		$json = json_encode($subjectParams);
		if (mb_strlen($json) > self::SUBJECT_PARAMS_MAX_LENGTH) {
			$params = json_decode(json_encode($subjectParams), true);

			if ($subject === self::SUBJECT_CARD_UPDATE_DESCRIPTION && isset($params['after'])) {
				$newContent = $params['after'];
				unset($params['before'], $params['after'], $params['card']['description']);

				$params['after'] = mb_substr($newContent, 0, self::SHORTENED_DESCRIPTION_MAX_LENGTH);
				if (mb_strlen($newContent) > self::SHORTENED_DESCRIPTION_MAX_LENGTH) {
					$params['after'] .= '...';
				}
				$subjectParams = $params;
			} else {
				throw new \Exception('Subject parameters too long');
			}
		}

		$event = $this->manager->generateEvent();
		$event->setApp('deck')
			->setType($eventType)
			->setAuthor($subjectParams['author'])
			->setObject($objectType, (int)$object->getId(), $object->getTitle())
			->setSubject($subject, $subjectParams)
			->setTimestamp(time());

		// FIXME: We currently require activities for comments even if they are disabled though settings
		// Get rid of this once the frontend fetches comments/activity individually
		if ($eventType === 'deck_comment') {
			$event->setAuthor(self::DECK_NOAUTHOR_COMMENT_SYSTEM_ENFORCED);
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
		if ($entity instanceof IComment) {
			$className = IComment::class;
		}
		$objectId = null;
		if ($objectType === self::DECK_OBJECT_CARD) {
			switch ($className) {
				case Card::class:
					$objectId = $entity->getId();
					break;
				case Attachment::class:
				case Label::class:
				case Assignment::class:
					$objectId = $entity->getCardId();
					break;
				case IComment::class:
					$objectId = $entity->getObjectId();
					break;
				default:
					throw new InvalidArgumentException('No entity relation present for ' . $className . ' to ' . $objectType);
			}
			return $this->cardMapper->find($objectId);
		}
		if ($objectType === self::DECK_OBJECT_BOARD) {
			switch ($className) {
				case Board::class:
					$objectId = $entity->getId();
					break;
				case Label::class:
				case Stack::class:
				case Acl::class:
					$objectId = $entity->getBoardId();
					break;
				default:
					throw new InvalidArgumentException('No entity relation present for ' . $className . ' to ' . $objectType);
			}
			return $this->boardMapper->find($objectId);
		}
		throw new InvalidArgumentException('No entity relation present for ' . $className . ' to ' . $objectType);
	}

	private function findDetailsForStack($stackId) {
		$stack = $this->stackMapper->find($stackId);
		$board = $this->boardMapper->find($stack->getBoardId());
		return [
			'stack' => $stack,
			'board' => $board
		];
	}

	private function findDetailsForCard($cardId, $subject = null) {
		$card = $this->cardMapper->find($cardId);
		$stack = $this->stackMapper->find($card->getStackId());
		$board = $this->boardMapper->find($stack->getBoardId());
		if ($subject !== self::SUBJECT_CARD_UPDATE_DESCRIPTION) {
			$card = [
				'id' => $card->getId(),
				'title' => $card->getTitle(),
				'archived' => $card->getArchived()
			];
		}
		return [
			'card' => $card,
			'stack' => $stack,
			'board' => $board
		];
	}

	private function findDetailsForAttachment($attachment) {
		$data = $this->findDetailsForCard($attachment->getCardId());
		return array_merge($data, ['attachment' => $attachment]);
	}

	private function findDetailsForAcl($aclId) {
		$acl = $this->aclMapper->find($aclId);
		$board = $this->boardMapper->find($acl->getBoardId());
		return [
			'acl' => $acl,
			'board' => $board
		];
	}

	public function canSeeCardActivity(int $cardId, string $userId): bool {
		try {
			$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ, $userId);
			$card = $this->cardMapper->find($cardId);
			return $card->getDeletedAt() === 0;
		} catch (NoPermissionException $e) {
			return false;
		}
	}

	public function canSeeBoardActivity(int $boardId, string $userId): bool {
		try {
			$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ, $userId);
			$board = $this->boardMapper->find($boardId);
			return $board->getDeletedAt() === 0;
		} catch (NoPermissionException $e) {
			return false;
		}
	}
}
