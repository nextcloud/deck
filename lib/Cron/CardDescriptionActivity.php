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


namespace OCA\Deck\Cron;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\Job;
use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Db\CardMapper;

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
