<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Listeners;

use OCA\Deck\Db\Card;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Provider\TextContextProvider;
use OCA\Deck\Provider\TextContextProviderFactory;
use OCA\Text\Event\DocumentContentUpdated;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TextDocumentContentListenerTest extends TestCase {
	private IEventDispatcher&MockObject $dispatcher;
	private TextContextProviderFactory&MockObject $factory;
	private TextDocumentContentListener $listener;

	protected function setUp(): void {
		parent::setUp();
		if (!class_exists(DocumentContentUpdated::class)) {
			$this->markTestSkipped('Text app not available');
		}
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->factory = $this->createMock(TextContextProviderFactory::class);
		$this->listener = new TextDocumentContentListener($this->dispatcher, $this->factory);
	}

	private function event(string $before, string $after): CardUpdatedEvent {
		$old = new Card();
		$old->setId(5);
		$old->setDescription($before);
		$new = new Card();
		$new->setId(5);
		$new->setDescription($after);
		return new CardUpdatedEvent($new, $old);
	}

	public function testDispatchesOnDescriptionChange(): void {
		$context = $this->createMock(TextContextProvider::class);
		$this->factory->expects($this->once())->method('buildForCard')->with(5)->willReturn($context);
		$this->dispatcher->expects($this->once())->method('dispatchTyped')
			->with($this->callback(fn ($e) => $e instanceof DocumentContentUpdated && $e->getContext() === $context));
		$this->listener->handle($this->event('a', 'b'));
	}

	public function testIgnoresUnchangedDescription(): void {
		$this->dispatcher->expects($this->never())->method('dispatchTyped');
		$this->listener->handle($this->event('a', 'a'));
	}
}
