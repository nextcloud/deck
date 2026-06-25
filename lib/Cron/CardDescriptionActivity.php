<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Deck\Cron;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Db\CardMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\Job;

class CardDescriptionActivity extends Job {

	/** @var ActivityManager */
	private $activityManager;
	/** @var CardMapper */
	private $cardMapper;

	public function __construct(ITimeFactory $time, ActivityManager $activityManager, CardMapper $cardMapper) {
		parent::__construct($time);
		$this->activityManager = $activityManager;
		$this->cardMapper = $cardMapper;
	}

	/**
	 * @param $argument
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function run($argument) {
		$cards = $this->cardMapper->findUnexposedDescriptionChances();
		foreach ($cards as $card) {
			$this->activityManager->triggerEvent(
				ActivityManager::DECK_OBJECT_CARD,
				$card,
				ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION,
				[
					'before' => $card->getDescriptionPrev(),
					'after' => $card->getDescription()
				],
				$card->getLastEditor()
			);

			$card->setDescriptionPrev(null);
			$card->setLastEditor(null);
			$this->cardMapper->update($card, false);
		}
	}
}
