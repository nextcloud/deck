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

use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Notification\NotificationHelper;
use OCP\Comments\CommentsEvent;
use OCP\Comments\IComment;
use \OCP\Comments\ICommentsEventHandler;

class CommentEventHandler implements ICommentsEventHandler {

	/** @var ActivityManager */
	private $activityManager;

	/** @var NotificationHelper */
	private $notificationHelper;

	/** @var CardMapper */
	private $cardMapper;

	/** @var ChangeHelper */
	private $changeHelper;

	public function __construct(ActivityManager $activityManager, NotificationHelper $notificationHelper, CardMapper $cardMapper, ChangeHelper $changeHelper) {
		$this->notificationHelper = $notificationHelper;
		$this->activityManager = $activityManager;
		$this->cardMapper = $cardMapper;
		$this->changeHelper = $changeHelper;
	}

	/**
	 * @param CommentsEvent $event
	 */
	public function handle(CommentsEvent $event) {
		if ($event->getComment()->getObjectType() !== 'deckCard') {
			return;
		}

		$this->changeHelper->cardChanged($event->getComment()->getObjectId());

		$eventType = $event->getEvent();
		if ($eventType === CommentsEvent::EVENT_ADD
		) {
			$this->notificationHandler($event);
			$this->activityHandler($event);
			return;
		}

		$applicableEvents = [
			CommentsEvent::EVENT_UPDATE
		];
		if (in_array($eventType, $applicableEvents)) {
			$this->notificationHandler($event);
			return;
		}
	}

	/**
	 * @param CommentsEvent $event
	 */
	private function activityHandler(CommentsEvent $event) {
		/** @var IComment $comment */
		$comment = $event->getComment();
		$card = $this->cardMapper->find($comment->getObjectId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_COMMENT_CREATE, ['comment' => $comment]);
	}

	/**
	 * @param CommentsEvent $event
	 */
	private function notificationHandler(CommentsEvent $event) {
		$this->notificationHelper->sendMention($event->getComment());
	}
}
