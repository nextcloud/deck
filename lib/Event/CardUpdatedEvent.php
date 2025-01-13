<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Event;

use OCA\Deck\Db\Card;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

class CardUpdatedEvent extends ACardEvent implements IWebhookCompatibleEvent {
	private $cardBefore;

	public function __construct(Card $card, ?Card $before = null) {
		parent::__construct($card);
		$this->cardBefore = $before;
	}

	public function getCardBefore() {
		return $this->cardBefore;
	}

	public function getWebhookSerializable(): array {
		return [
			'before' => $this->getCardBefore()->toEventData()->jsonSerialize(),
			'after' => $this->getCard()->toEventData()->jsonSerialize()
		];
	}
}
