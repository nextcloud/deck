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

/**
 * Created by PhpStorm.
 * User: jus
 * Date: 16.05.17
 * Time: 12:34
 */

namespace OCA\Deck\Cron;

use DateTime;
use OC\BackgroundJob\Job;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCP\IUser;
use OCP\Notification\IManager;

class ScheduledNotifications extends Job {

	/** @var CardMapper */
	protected $cardMapper;
	/** @var BoardMapper */
	protected $boardMapper;
	/** @var IManager */
	protected $notificationManager;
	/** @var array */
	private $users = [];
	/** @var array */
	private $boards = [];

	public function __construct(
		CardMapper $cardMapper,
		BoardMapper $boardMapper,
		IManager $notificationManager
	) {
		$this->cardMapper = $cardMapper;
		$this->boardMapper = $boardMapper;
		$this->notificationManager = $notificationManager;
	}

	public function run($argument) {
		// Notify board owner and card creator about overdue cards
		// TODO: Once assigning users is possible, those should be notified instead of all users of the board
		$cards = $this->cardMapper->findOverdue();
		/** @var Card $card */
		foreach ($cards as $card) {
			// check if notification has already been sent
			// ideally notifications should not be deleted once seen by the user so we can
			// also deliver due date notifications for users who have been added later to a board
			// this should maybe be addressed in nextcloud/server
			if ($card->getNotified()) {
				continue;
			}
			$boardId = $this->cardMapper->findBoardId($card->getId());
			/** @var IUser $user */
			foreach ($this->getUsers($boardId) as $user) {
				$this->sendNotification($user, $card, $boardId);
			}
			$this->cardMapper->markNotified($card);
		}
	}

	private function getUsers($boardId) {
		// cache users of a board so we don't query them for every cards
		if (array_key_exists((string)$boardId, $this->users)) {
			return $this->users[(string)$boardId];
		}
		$this->boards[(string)$boardId] = $this->boardMapper->find($boardId, false, true);
		$users = [$this->boards[(string)$boardId]->getOwner()];
		/** @var Acl $acl */
		foreach ($this->boards[(string)$boardId]->getAcl() as $acl) {
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
				$users[] = $acl->getParticipant();
			}
			if ($acl->getType() === Acl::PERMISSION_TYPE_GROUP) {
				$group = \OC::$server->getGroupManager()->get($acl->getParticipant());
				/** @var IUser $user */
				foreach ($group->getUsers() as $user) {
					$users[] = $user->getUID();
				}
			}
		}
		$this->users[(string)$boardId] = array_unique($users);
		return $this->users[(string)$boardId];
	}

	private function sendNotification($user, $card, $boardId) {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('deck')
			->setUser($user)
			->setObject('card', $card->getId())
			->setSubject('card-overdue', [$card->getTitle(), $this->boards[(string)$boardId]->getTitle()]);
		// this is only needed, if a notification exists for a user and the notified attribute is not set on the card
		// if ($this->notificationManager->getCount($notification) > 0)
		//	return;
		$notification
			->setDateTime(new DateTime($card->getDuedate()));
		$this->notificationManager->notify($notification);
	}

}