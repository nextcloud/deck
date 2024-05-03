<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Activity;

use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Notification\NotificationHelper;
use OCP\Comments\CommentsEvent;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsEventHandler;

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
