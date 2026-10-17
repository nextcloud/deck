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
use Test\TestCase;

class LoadBoardWebComponentListenerTest extends TestCase {
	public function testIgnoresUnrelatedEvents(): void {
		$state = $this->createMock(IInitialState::class);
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$state->expects($this->never())->method('provideInitialState');
		$dispatcher->expects($this->never())->method('dispatchTyped');

		(new LoadBoardWebComponentListener($state, $dispatcher))->handle(new Event());
	}

	public function testLoadsUploadStateAndIntegrations(): void {
		$state = $this->createMock(IInitialState::class);
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$state->expects($this->once())->method('provideInitialState')
			->with('maxUploadSize', $this->isType('int'));
		$events = [];
		$dispatcher->method('dispatchTyped')->willReturnCallback(
			static function (Event $event) use (&$events): void {
				$events[] = get_class($event);
			}
		);

		(new LoadBoardWebComponentListener($state, $dispatcher))->handle(new LoadBoardWebComponentEvent());

		$expected = [LoadSidebar::class, LoadAdditionalScriptsEvent::class];
		if (class_exists(LoadEditor::class)) {
			$expected[] = LoadEditor::class;
		}
		if (class_exists(LoadViewer::class)) {
			$expected[] = LoadViewer::class;
		}
		$this->assertSame($expected, $events);
	}
}
