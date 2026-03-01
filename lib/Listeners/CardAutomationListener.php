<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Listeners;

use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Service\StackAutomationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<CardCreatedEvent|CardUpdatedEvent|CardDeletedEvent>
 */
class CardAutomationListener implements IEventListener {
	public function __construct(
		private StackAutomationService $automationService,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof CardCreatedEvent) {
			$this->handleCardCreated($event);
		} elseif ($event instanceof CardUpdatedEvent) {
			$this->handleCardUpdated($event);
		} elseif ($event instanceof CardDeletedEvent) {
			$this->handleCardDeleted($event);
		}
	}

	private function handleCardCreated(CardCreatedEvent $event): void {
		try {
			$card = $event->getCard();
			$this->automationService->executeAutomationsForEvent($event, 'create', $card->getStackId());
		} catch (\Exception $e) {
			$this->logger->error('Failed to execute automations on card create', ['exception' => $e]);
		}
	}

	private function handleCardUpdated(CardUpdatedEvent $event): void {
		try {
			$card = $event->getCard();
			$cardBefore = $event->getCardBefore();
			
			if ($cardBefore === null) {
				return;
			}

			$oldStackId = $cardBefore->getStackId();
			$newStackId = $card->getStackId();

			// Check if card moved between stacks
			if ($oldStackId !== $newStackId) {
				// Execute EXIT on old stack
				$this->automationService->executeAutomationsForEvent($event, 'exit', $oldStackId);
				// Execute ENTER on new stack
				$this->automationService->executeAutomationsForEvent($event, 'enter', $newStackId);
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to execute automations on card update', ['exception' => $e]);
		}
	}

	private function handleCardDeleted(CardDeletedEvent $event): void {
		try {
			$card = $event->getCard();
			$this->automationService->executeAutomationsForEvent($event, 'delete', $card->getStackId());
		} catch (\Exception $e) {
			$this->logger->error('Failed to execute automations on card delete', ['exception' => $e]);
		}
	}
}
