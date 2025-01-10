<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Cron;

use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Notification\NotificationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\Job;
use Psr\Log\LoggerInterface;

class ScheduledNotifications extends Job {

	public function __construct(
		ITimeFactory $time,
		protected CardMapper $cardMapper,
		protected NotificationHelper $notificationHelper,
		protected LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param $argument
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function run($argument) {
		// Notify board owner and card creator about overdue cards
		$cards = $this->cardMapper->findOverdue();
		/** @var Card $card */
		foreach ($cards as $card) {
			try {
				$this->notificationHelper->sendCardDuedate($card);
			} catch (DoesNotExistException $e) {
				// Skip if any error occurs
				$this->logger->debug('Could not create overdue notification for card with id ' . $card->getId());
			}
		}
	}
}
