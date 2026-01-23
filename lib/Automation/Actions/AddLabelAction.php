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
use OCA\Deck\Db\LabelMapper;
use Psr\Log\LoggerInterface;

class AddLabelAction implements ActionInterface {
	public function __construct(
		private CardMapper $cardMapper,
		private LabelMapper $labelMapper,
		private LoggerInterface $logger,
	) {
	}

	public function execute(Card $card, AutomationEvent $event, array $config = []): void {
		$labelIds = $config['labelIds'] ?? [];

		if (empty($labelIds) || !is_array($labelIds)) {
			$this->logger->warning('AddLabelAction: Missing or invalid labelIds in configuration');
			return;
		}

		try {
			// Get currently assigned labels
			$assignedLabels = $this->labelMapper->findAssignedLabelsForCard($card->getId());
			$assignedLabelIds = array_map(fn($label) => $label->getId(), $assignedLabels);

			foreach ($labelIds as $labelId) {
				// Only add if not already assigned
				if (!in_array((int)$labelId, $assignedLabelIds, true)) {
					$this->cardMapper->assignLabel($card->getId(), (int)$labelId);
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('AddLabelAction failed: ' . $e->getMessage(), ['exception' => $e]);
			throw $e;
		}
	}

	public function validateConfig(array $config): bool {
		return isset($config['labelIds'])
			&& is_array($config['labelIds'])
			&& !empty($config['labelIds'])
			&& array_reduce($config['labelIds'], fn($valid, $id) => $valid && is_numeric($id), true);
	}

	public function isApplicableForEvent(AutomationEvent $event): bool {
		return $event->getEventName() !== AutomationEvent::EVENT_DELETE;
	}

	public function getDescription(array $config): string {
		$labelIds = $config['labelIds'] ?? [];
		$count = count($labelIds);
		return $count > 0 ? "Add {$count} label(s)" : "Add labels";
	}
}
