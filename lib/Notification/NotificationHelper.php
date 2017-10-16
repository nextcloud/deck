<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Notification;

use DateTime;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\PermissionService;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Notification\IManager;

class NotificationHelper {

	/** @var CardMapper */
	protected $cardMapper;
	/** @var BoardMapper */
	protected $boardMapper;
	/** @var PermissionService */
	protected $permissionService;
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
		PermissionService $permissionService,
		IManager $notificationManager,
		IGroupManager $groupManager,
		$userId
	) {
		$this->cardMapper = $cardMapper;
		$this->boardMapper = $boardMapper;
		$this->permissionService = $permissionService;
		$this->notificationManager = $notificationManager;
		$this->groupManager = $groupManager;
		$this->currentUser = $userId;
	}

	public function sendCardDuedate($card) {
		// check if notification has already been sent
		// ideally notifications should not be deleted once seen by the user so we can
		// also deliver due date notifications for users who have been added later to a board
		// this should maybe be addressed in nextcloud/server
		if ($card->getNotified()) {
			return;
		}

		// TODO: Once assigning users is possible, those should be notified instead of all users of the board
		$boardId = $this->cardMapper->findBoardId($card->getId());
		$board = $this->getBoard($boardId);
		/** @var IUser $user */
		foreach ($this->permissionService->findUsers($boardId) as $user) {
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp('deck')
				->setUser($user->getUID())
				->setObject('card', $card->getId())
				->setSubject('card-overdue', [
					$card->getTitle(), $board->getTitle()
				])
				->setDateTime(new DateTime($card->getDuedate()));
			$this->notificationManager->notify($notification);
		}
		$this->cardMapper->markNotified($card);
	}

	/**
	 * Send notifications that a board was shared with a user/group
	 *
	 * @param $boardId
	 * @param $acl
	 * @throws \InvalidArgumentException
	 */
	public function sendBoardShared($boardId, $acl) {
		$board = $this->getBoard($boardId);
		if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
			$notification = $this->generateBoardShared($board, $acl->getParticipant());
			$this->notificationManager->notify($notification);
		}
		if ($acl->getType() === Acl::PERMISSION_TYPE_GROUP) {
			$group = $this->groupManager->get($acl->getParticipant());
			foreach ($group->getUsers() as $user) {
				$notification = $this->generateBoardShared($board, $user->getUID());
				$this->notificationManager->notify($notification);
			}
		}
	}

	/**
	 * @param $boardId
	 * @return Board
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	private function getBoard($boardId) {
		if (!array_key_exists($boardId, $this->boards)) {
			$this->boards[$boardId] = $this->boardMapper->find($boardId);
		}
		return $this->boards[$boardId];
	}

	private function generateBoardShared($board, $userId) {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('deck')
			->setUser($userId)
			->setDateTime(new DateTime())
			->setObject('board', $board->getId())
			->setSubject('board-shared', [$board->getTitle(), $this->currentUser]);
		return $notification;
	}

}