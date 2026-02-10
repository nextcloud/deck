<?php

namespace OCA\Deck\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\OCM\Events\ResourceTypeRegisterEvent;
use OCP\OCM\IOCMProvider;

class ResourceTypeRegisterListener implements IEventListener {
	public function __construct(
		protected IOCMProvider $provider,
	) {
	}

	public function handle(Event $event):void {
		if (!$event instanceof ResourceTypeRegisterEvent) {
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
