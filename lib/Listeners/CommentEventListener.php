<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Listeners;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Notification\NotificationHelper;
use OCP\Comments\CommentsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<CommentsEvent|Event> */
class CommentEventListener implements IEventListener {

	public function __construct(
		private ActivityManager $activityManager,
		private NotificationHelper $notificationHelper,
		private CardMapper $cardMapper,
		private ChangeHelper $changeHelper,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof CommentsEvent) {
			return;
		}

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

	private function activityHandler(CommentsEvent $event): void {
		$comment = $event->getComment();
		$card = $this->cardMapper->find($comment->getObjectId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_COMMENT_CREATE, ['comment' => $comment], $comment->getActorId());
	}

	private function notificationHandler(CommentsEvent $event): void {
		$this->notificationHelper->sendMention($event->getComment());
	}
}
