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

namespace OCA\Deck\Cron;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\Job;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Notification\NotificationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\ILogger;

class ScheduledNotifications extends Job {

	/** @var CardMapper */
	protected $cardMapper;
	/** @var NotificationHelper */
	protected $notificationHelper;
	/** @var ILogger */
	protected $logger;

	public function __construct(
		ITimeFactory $time,
		CardMapper $cardMapper,
		NotificationHelper $notificationHelper,
		ILogger $logger
	) {
		parent::__construct($time);
		$this->cardMapper = $cardMapper;
		$this->notificationHelper = $notificationHelper;
		$this->logger = $logger;
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
