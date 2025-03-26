<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Event;

use OCA\Deck\Db\Card;

class CardUpdatedEvent extends ACardEvent {
	private $cardBefore;

	public function __construct(Card $card, ?Card $before = null) {
		parent::__construct($card);
		$this->cardBefore = $before;
	}

	public function getCardBefore() {
		return $this->cardBefore;
	}
}
