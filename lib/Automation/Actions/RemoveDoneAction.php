<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Automation\Actions;

use OCA\Deck\Automation\ActionInterface;
use OCA\Deck\Automation\AutomationEvent;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use Psr\Log\LoggerInterface;

class RemoveDoneAction implements ActionInterface {
	public function __construct(
		private CardMapper $cardMapper,
		private LoggerInterface $logger,
	) {
	}

	public function execute(Card $card, AutomationEvent $event, array $config = []): void {
		try {
			$card->setDone(null);
			$this->cardMapper->update($card);
		} catch (\Exception $e) {
			$this->logger->error('RemoveDoneAction failed: ' . $e->getMessage(), ['exception' => $e]);
			throw $e;
		}
	}

	public function validateConfig(array $config): bool {
		// No configuration needed
		return true;
	}

	public function isApplicableForEvent(AutomationEvent $event): bool {
		return $event->getEventName() !== AutomationEvent::EVENT_DELETE;
	}

	public function getDescription(array $config): string {
		return "Unmark card as done";
	}
}
