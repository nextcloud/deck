<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Provider;

use OC\Security\SecureRandom;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Text\Context\IContext;
use PHPUnit\Framework\TestCase;

class TextContextProviderFactoryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		if (!interface_exists(IContext::class)) {
			$this->markTestSkipped('Text app not available');
		}
	}

	/**
	 * Text dispatches its context event on public share requests too, where there is no user
	 */
	public function testBuildForCardWithoutUser(): void {
		$factory = new TextContextProviderFactory(
			$this->createMock(SecureRandom::class),
			$this->createMock(CardService::class),
			$this->createMock(CardMapper::class),
			$this->createMock(PermissionService::class),
			null,
		);

		$this->assertSame(5, $factory->buildForCard(5)->getId());
	}
}
