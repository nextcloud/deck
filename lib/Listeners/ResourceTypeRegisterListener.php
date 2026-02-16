<?php

namespace OCA\Deck\Listeners;

use OCA\Deck\Exceptions\FederationDisabledException;
use OCA\Deck\Service\ConfigService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\OCM\Events\ResourceTypeRegisterEvent;
use OCP\OCM\IOCMProvider;

class ResourceTypeRegisterListener implements IEventListener {
	public function __construct(
		protected IOCMProvider $provider,
		protected ConfigService $configService
	) {
	}

	public function handle(Event $event):void {
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
