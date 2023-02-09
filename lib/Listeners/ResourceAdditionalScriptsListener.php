<?php

namespace OCA\Deck\Listeners;

use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Util;

/** @template-implements IEventListener<Event|LoadAdditionalScriptsEvent> */
class ResourceAdditionalScriptsListener implements IEventListener {
	private IRequest $request;

	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		if (strpos($this->request->getPathInfo(), '/call/') === 0) {
			// Talk integration has its own entrypoint which already includes collections handling
			return;
		}

		Util::addScript('deck', 'deck-collections');
	}
}
