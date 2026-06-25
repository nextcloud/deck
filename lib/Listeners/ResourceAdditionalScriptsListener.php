<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
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

		if (str_starts_with($this->request->getPathInfo(), '/call/')) {
			// Talk integration has its own entrypoint which already includes collections handling
			return;
		}

		Util::addScript('deck', 'deck-collections');
	}
}
