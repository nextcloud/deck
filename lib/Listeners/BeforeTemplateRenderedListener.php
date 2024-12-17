<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Listeners;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Util;

/** @template-implements IEventListener<Event|BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {
	private $request;

	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if (!$event->isLoggedIn()) {
			return;
		}
		Util::addStyle('deck', 'deck');

		$pathInfo = $this->request->getPathInfo();
		if (str_starts_with($pathInfo, '/apps/calendar')) {
			Util::addScript('deck', 'deck-calendar');
		}

		if (str_starts_with($pathInfo, '/call/') || str_starts_with($pathInfo, '/apps/spreed')) {
			Util::addScript('deck', 'deck-talk');
		}
	}
}
