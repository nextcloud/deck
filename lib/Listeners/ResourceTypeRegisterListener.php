<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Listeners;

use OCA\Deck\Exceptions\FederationDisabledException;
use OCA\Deck\Service\ConfigService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\OCM\Events\ResourceTypeRegisterEvent;
use OCP\OCM\IOCMProvider;

/**
 * @template-implements IEventListener<Event>
 */
class ResourceTypeRegisterListener implements IEventListener {
	public function __construct(
		protected IOCMProvider $provider,
		protected ConfigService $configService,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof ResourceTypeRegisterEvent) {
			return;
		}

		try {
			$this->configService->ensureFederationEnabled();
		} catch (FederationDisabledException $e) {
			return;
		}

		$event->registerResourceType(
			'deck',
			['user'],
			[
				'deck-v1' => '/ocs/v2.php/apps/deck/api/',
			]
		);
	}
}
