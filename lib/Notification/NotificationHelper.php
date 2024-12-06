<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Notification;

use DateTime;
use Exception;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\User;
use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Comments\IComment;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

class NotificationHelper {

	/** @var CardMapper */
	protected $cardMapper;
	/** @var BoardMapper */
	protected $boardMapper;
	/** @var AssignmentMapper */
	protected $assignmentMapper;
	/** @var PermissionService */
	protected $permissionService;
	/** @var IConfig */
	protected $config;
	/** @var IManager */
	protected $notificationManager;
	/** @var IGroupManager */
	protected $groupManager;
	/** @var string */
	protected $currentUser;
	/** @var array */
	private $boards = [];

	public function __construct(
		CardMapper $cardMapper,
		BoardMapper $boardMapper,
		AssignmentMapper $assignmentMapper,
		PermissionService $permissionService,
		IConfig $config,
		IManager $notificationManager,
		IGroupManager $groupManager,
		$userId,
	) {
		$this->cardMapper = $cardMapper;
		$this->boardMapper = $boardMapper;
		$this->assignmentMapper = $assignmentMapper;
		$this->permissionService = $permissionService;
		$this->config = $config;
		$this->notificationManager = $notificationManager;
		$this->groupManager = $groupManager;
		$this->currentUser = $userId;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws Exception thrown on invalid due date
	 */
	public function sendCardDuedate(Card $card): void {
		// check if notification has already been sent
		// ideally notifications should not be deleted once seen by the user so we can
		// also deliver due date notifications for users who have been added later to a board
		// this should maybe be addressed in nextcloud/server
		if ($card->getNotified()) {
			return;
		}

		$boardId = $this->cardMapper->findBoardId($card->getId());
		$board = $this->getBoard($boardId, false, true);
		/** @var User $user */
		foreach ($this->permissionService->findUsers($boardId) as $user) {
			$notificationSetting = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'board:' . $boardId . ':notify-due', ConfigService::SETTING_BOARD_NOTIFICATION_DUE_DEFAULT);

			if ($notificationSetting === ConfigService::SETTING_BOARD_NOTIFICATION_DUE_OFF) {
				continue;
			}

			$shouldNotify = $notificationSetting === ConfigService::SETTING_BOARD_NOTIFICATION_DUE_ALL;

			if ($user->getUID() === $board->getOwner() && count($board->getAcl()) === 0) {
				// Notify if all or assigned is configured for unshared boards
				$shouldNotify = true;
			} elseif ($notificationSetting === ConfigService::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED && $this->assignmentMapper->isUserAssigned($card->getId(), $user->getUID())) {
				// Notify if the user is assigned and has the assigned setting selected
				$shouldNotify = true;
			}

			if ($shouldNotify) {
				$notification = $this->notificationManager->createNotification();
				$notification
					->setApp('deck')
					->setUser((string)$user->getUID())
					->setObject('card', (string)$card->getId())
					->setSubject('card-overdue', [
						$card->getTitle(), $board->getTitle()
					])
					->setDateTime($card->getDuedate());
				$this->notificationManager->notify($notification);
			}
		}
		$this->cardMapper->markNotified($card);
	}

	public function markDuedateAsRead(Card $card): void {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('deck')
			->setObject('card', (string)$card->getId())
			->setSubject('card-overdue', []);
		$this->notificationManager->markProcessed($notification);
	}

	public function sendCardAssigned(Card $card, string $userId): void {
		$boardId = $this->cardMapper->findBoardId($card->getId());
		try {
			$board = $this->getBoard($boardId);
		} catch (Exception $e) {
			return;
		}

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('deck')
			->setUser($userId)
			->setDateTime(new DateTime())
			->setObject('card', (string)$card->getId())
			->setSubject('card-assigned', [
				$card->getTitle(),
				$board->getTitle(),
				$this->currentUser
			]);
		$this->notificationManager->notify($notification);
	}

	public function markCardAssignedAsRead(Card $card, string $userId): void {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('deck')
			->setUser($userId)
			->setObject('card', (string)$card->getId())
			->setSubject('card-assigned', []);
		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * Send notifications that a board was shared with a user/group
	 */
	public function sendBoardShared(int $boardId, Acl $acl, bool $markAsRead = false): void {
		try {
			$board = $this->getBoard($boardId);
		} catch (Exception $e) {
			return;
		}

		if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
			$notification = $this->generateBoardShared($board, $acl->getParticipant());
			if ($markAsRead) {
				$this->notificationManager->markProcessed($notification);
			} else {
				$notification->setDateTime(new DateTime());
				$this->notificationManager->notify($notification);
			}
		}
		if ($acl->getType() === Acl::PERMISSION_TYPE_GROUP) {
			$group = $this->groupManager->get($acl->getParticipant());
			if ($group === null) {
				return;
			}
			foreach ($group->getUsers() as $user) {
				if ($user->getUID() === $this->currentUser) {
					continue;
				}
				$notification = $this->generateBoardShared($board, $user->getUID());
				if ($markAsRead) {
					$this->notificationManager->markProcessed($notification);
				} else {
					$notification->setDateTime(new DateTime());
					$this->notificationManager->notify($notification);
				}
			}
		}
	}

	public function sendMention(IComment $comment): void {
		foreach ($comment->getMentions() as $mention) {
			$card = $this->cardMapper->find($comment->getObjectId());
			$boardId = $this->cardMapper->findBoardId($card->getId());
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp('deck')
				->setUser((string)$mention['id'])
				->setDateTime(new DateTime())
				->setObject('card', (string)$card->getId())
				->setSubject('card-comment-mentioned', [$card->getTitle(), $boardId, $this->currentUser])
				->setMessage('{message}', ['message' => $comment->getMessage()]);
			$this->notificationManager->notify($notification);
		}
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function getBoard(int $boardId, bool $withLabels = false, bool $withAcl = false): Board {
		if (!array_key_exists($boardId, $this->boards)) {
			$this->boards[$boardId] = $this->boardMapper->find($boardId, $withLabels, $withAcl);
		}
		return $this->boards[$boardId];
	}

	private function generateBoardShared(Board $board, string $userId): INotification {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('deck')
			->setUser($userId)
			->setObject('board', (string)$board->getId())
			->setSubject('board-shared', [$board->getTitle(), $this->currentUser]);
		return $notification;
	}
}
