<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Automation\Actions;

use OCA\Deck\Automation\ActionInterface;
use OCA\Deck\Automation\AutomationEvent;
use OCA\Deck\Db\Card;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class WebhookAction implements ActionInterface {
	public function __construct(
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	public function execute(Card $card, AutomationEvent $event, array $config = []): void {
		$url = $config['url'] ?? null;
		$method = $config['method'] ?? 'POST';
		$headers = $config['headers'] ?? [];

		if ($url === null || !filter_var($url, FILTER_VALIDATE_URL)) {
			$this->logger->warning('WebhookAction: Invalid or missing URL in configuration');
			return;
		}

		try {
			$client = $this->clientService->newClient();

			$options = [
				'headers' => $headers,
				'timeout' => 10,
			];

			if ($method === 'POST') {
				$payload = [
					'cardId' => $card->getId(),
					'cardTitle' => $card->getTitle(),
					'stackId' => $card->getStackId(),
					'event' => $event->getEventName(),
					'fromStackId' => $event->getFromStackId(),
					'toStackId' => $event->getToStackId(),
				];

				$options['json'] = $payload;
				$client->post($url, $options);
			} elseif ($method === 'GET') {
				$client->get($url, $options);
			} else {
				$this->logger->warning('WebhookAction: Unsupported HTTP method: ' . $method);
			}
		} catch (\Exception $e) {
			$this->logger->error('WebhookAction failed: ' . $e->getMessage(), ['exception' => $e]);
			// Don't throw - webhook failures shouldn't block card operations
		}
	}

	public function validateConfig(array $config): bool {
		if (!isset($config['url'])) {
			return false;
		}
		if (!filter_var($config['url'], FILTER_VALIDATE_URL)) {
			return false;
		}
		if (isset($config['method']) && !in_array($config['method'], ['GET', 'POST'])) {
			return false;
		}
		return true;
	}

	public function isApplicableForEvent(AutomationEvent $event): bool {
		return true;
	}

	public function getDescription(array $config): string {
		$url = $config['url'] ?? 'unknown';
		$method = $config['method'] ?? 'POST';
		return "Call webhook {$method} {$url}";
	}
}
