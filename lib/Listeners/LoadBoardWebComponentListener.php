<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Listeners;

use OCA\Deck\Event\LoadBoardWebComponentEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Text\Event\LoadEditor;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<LoadBoardWebComponentEvent> */
class LoadBoardWebComponentListener implements IEventListener {
	public function __construct(
		private IInitialState $initialState,
		private IEventDispatcher $eventDispatcher,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadBoardWebComponentEvent) {
			return;
		}

		$this->initialState->provideInitialState('maxUploadSize', (int)Util::uploadLimit());
		Util::addStyle('deck', 'deck');
		Util::addScript('deck', 'deck-boardComponent');
		$this->eventDispatcher->dispatchTyped(new LoadSidebar());
		$this->eventDispatcher->dispatchTyped(new LoadAdditionalScriptsEvent());
		if (class_exists(LoadEditor::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadEditor());
		}
		if (class_exists(LoadViewer::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadViewer());
		}
	}
}
