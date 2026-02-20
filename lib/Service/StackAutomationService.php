<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Automation\ActionFactory;
use OCA\Deck\Automation\AutomationEvent;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\StackAutomation;
use OCA\Deck\Db\StackAutomationMapper;
use Psr\Log\LoggerInterface;

class StackAutomationService {
	public function __construct(
		private StackAutomationMapper $automationMapper,
		private ActionFactory $actionFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get all automations for a stack
	 *
	 * @param int $stackId
	 * @return StackAutomation[]
	 */
	public function getAutomations(int $stackId): array {
		return $this->automationMapper->findByStackId($stackId);
	}

	/**
	 * Create a new automation rule
	 */
	public function createAutomation(int $stackId, string $event, string $actionType, array $config, int $order = 0): StackAutomation {
		// Validate action type
		if (!in_array($actionType, $this->actionFactory->getSupportedActions())) {
			throw new \InvalidArgumentException('Unsupported action type: ' . $actionType);
		}

		// Validate configuration
		$action = $this->actionFactory->createAction($actionType);
		if ($action && !$action->validateConfig($config)) {
			throw new \InvalidArgumentException('Invalid configuration for action: ' . $actionType);
		}

		// Validate event
		if (!in_array($event, [AutomationEvent::EVENT_CREATE, AutomationEvent::EVENT_DELETE, AutomationEvent::EVENT_ENTER, AutomationEvent::EVENT_EXIT])) {
			throw new \InvalidArgumentException('Invalid event: ' . $event);
		}

		$automation = new StackAutomation();
		$automation->setStackId($stackId);
		$automation->setEvent($event);
		$automation->setActionType($actionType);
		$automation->setActionConfigArray($config);
		$automation->setOrder($order);
		$automation->setCreatedAt(time());
		$automation->setUpdatedAt(time());

		return $this->automationMapper->insert($automation);
	}

	/**
	 * Update an existing automation rule
	 */
	public function updateAutomation(int $id, string $event, string $actionType, array $config, int $order): StackAutomation {
		$automation = $this->automationMapper->find($id);

		// Validate action type
		if (!in_array($actionType, $this->actionFactory->getSupportedActions())) {
			throw new \InvalidArgumentException('Unsupported action type: ' . $actionType);
		}

		// Validate configuration
		$action = $this->actionFactory->createAction($actionType);
		if ($action && !$action->validateConfig($config)) {
			throw new \InvalidArgumentException('Invalid configuration for action: ' . $actionType);
		}

		// Validate event
		if (!in_array($event, [AutomationEvent::EVENT_CREATE, AutomationEvent::EVENT_DELETE, AutomationEvent::EVENT_ENTER, AutomationEvent::EVENT_EXIT])) {
			throw new \InvalidArgumentException('Invalid event: ' . $event);
		}

		$automation->setEvent($event);
		$automation->setActionType($actionType);
		$automation->setActionConfigArray($config);
		$automation->setOrder($order);
		$automation->setUpdatedAt(time());

		return $this->automationMapper->update($automation);
	}

	/**
	 * Delete an automation rule
	 */
	public function deleteAutomation(int $id): void {
		$automation = $this->automationMapper->find($id);
		$this->automationMapper->delete($automation);
	}

	/**
	 * Execute automations for a specific event using Nextcloud event objects
	 */
	public function executeAutomationsForEvent(\OCP\EventDispatcher\Event $event, string $eventName, int $stackId): void {
		$card = null;
		
		if ($event instanceof \OCA\Deck\Event\CardCreatedEvent || 
		    $event instanceof \OCA\Deck\Event\CardUpdatedEvent || 
		    $event instanceof \OCA\Deck\Event\CardDeletedEvent) {
			$card = $event->getCard();
		}
		
		if ($card === null) {
			$this->logger->warning('Card not found in event for automation execution');
			return;
		}
		
		$this->executeAutomations($stackId, $eventName, $card);
	}

	/**
	 * Execute automations for a given stack and event
	 */
	public function executeAutomations(int $stackId, string $eventName, Card $card, ?int $fromStackId = null, ?int $toStackId = null): void {
		$this->logger->info('Executing automations for stack', [
			'stackId' => $stackId,
			'eventName' => $eventName,
			'cardId' => $card->getId(),
		]);

		$automations = $this->automationMapper->findByStackIdAndEvent($stackId, $eventName);
		
		$this->logger->info('Found automations', [
			'count' => count($automations),
			'automations' => array_map(fn($a) => ['id' => $a->getId(), 'event' => $a->getEvent(), 'actionType' => $a->getActionType()], $automations),
		]);

		if (empty($automations)) {
			return;
		}

		$event = new AutomationEvent($eventName, $fromStackId, $toStackId);

		foreach ($automations as $automation) {
			$action = $this->actionFactory->createAction($automation->getActionType());
			
			if ($action === null) {
				$this->logger->warning('Unknown action type: ' . $automation->getActionType());
				continue;
			}

			// Check if action is applicable for this event
			if (!$action->isApplicableForEvent($event)) {
				$this->logger->debug('Action not applicable for event', [
					'actionType' => $automation->getActionType(),
					'eventName' => $eventName,
				]);
				continue;
			}

			try {
				$config = $automation->getActionConfigArray();
				$action->execute($card, $event, $config);
				$this->logger->info('Automation executed successfully', [
					'automationId' => $automation->getId(),
					'actionType' => $automation->getActionType(),
					'cardId' => $card->getId(),
				]);
			} catch (\Exception $e) {
				$this->logger->error('Automation execution failed', [
					'automationId' => $automation->getId(),
					'actionType' => $automation->getActionType(),
					'cardId' => $card->getId(),
					'error' => $e->getMessage(),
				]);
				// Continue with other automations even if one fails
			}
		}
	}
}
