<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Event;

use OCA\Deck\Provider\TextContextProviderFactory;
use OCA\Text\Event\RegisterContextEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class RegisterTextContextEventListener implements IEventListener{
	public function __construct(
		private readonly TextContextProviderFactory $textContextProviderFactory,
	){}
	public function handle(Event $event): void{
		if (!$event instanceof RegisterContextEvent) {
			return;
		}

		$event->getContextManager()->registerContext(
			'deck_card',
			TextContextProviderFactory::class
		);
	}
}
