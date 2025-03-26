<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Event;

use OCA\Deck\Db\Card;
use OCP\EventDispatcher\Event;

abstract class ACardEvent extends Event {
	private $card;
	
	public function __construct(Card $card) {
		parent::__construct();

		$this->card = $card;
	}

	public function getCard(): Card {
		return $this->card;
	}
}
