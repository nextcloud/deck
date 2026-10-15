<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Provider;

use OC\Security\SecureRandom;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Text\Context\IContext;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TextContextProviderTest extends TestCase {
	private CardService&MockObject $cardService;
	private TextContextProvider $provider;

	protected function setUp(): void {
		parent::setUp();
		if (!interface_exists(IContext::class)) {
			$this->markTestSkipped('Text app not available');
		}
		$this->cardService = $this->createMock(CardService::class);
		$this->provider = new TextContextProvider(
			$this->createMock(SecureRandom::class),
			$this->cardService,
			$this->createMock(CardMapper::class),
			$this->createMock(PermissionService::class),
			'admin',
			5,
		);
	}

	public function testLoadContent(): void {
		$card = new Card();
		$card->setDescription('hello');
		$this->cardService->method('find')->with(5)->willReturn($card);

		$this->assertSame('hello', $this->provider->loadContent());
	}

	public function testLoadContentCardMissing(): void {
		$this->cardService->method('find')->willThrowException(new DoesNotExistException('missing'));

		$this->expectException(NotFoundException::class);
		$this->provider->loadContent();
	}

	public function testLoadContentNoPermission(): void {
		$this->cardService->method('find')->willThrowException(new NoPermissionException('denied'));

		$this->expectException(NotPermittedException::class);
		$this->provider->loadContent();
	}

	public function testBuildDocumentCardMissing(): void {
		$this->cardService->method('find')->willThrowException(new DoesNotExistException('missing'));

		$this->expectException(NotFoundException::class);
		$this->provider->buildDocument();
	}

	public function testBuildDocumentNoPermission(): void {
		$this->cardService->method('find')->willThrowException(new NoPermissionException('denied'));

		$this->expectException(NotPermittedException::class);
		$this->provider->buildDocument();
	}
}
