<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Listeners;

use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Provider\TextContextProviderFactory;
use OCA\Text\Event\DocumentContentUpdated;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;

/**
 * Tells Text that a card description changed so it can resolve open editing sessions.
 *
 * @template-implements IEventListener<Event|CardUpdatedEvent>
 */
class TextDocumentContentListener implements IEventListener {
	public function __construct(
		private readonly IEventDispatcher $eventDispatcher,
		private readonly TextContextProviderFactory $contextFactory,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof CardUpdatedEvent) || !class_exists(DocumentContentUpdated::class)) {
			return;
		}

		$before = $event->getCardBefore();
		$card = $event->getCard();
		if ($before === null || $before->getDescription() === $card->getDescription()) {
			return;
		}

		$this->eventDispatcher->dispatchTyped(
			new DocumentContentUpdated($this->contextFactory->buildForCard($card->getId()))
		);
	}
}
