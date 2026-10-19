<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Provider;

use OCA\Deck\Service\FullTextSearchService;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class DeckProviderTest extends TestCase {
	public function testSearchTemplateStylesheetIsShipped(): void {
		$provider = new DeckProvider(
			$this->createMock(IL10N::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(FullTextSearchService::class),
		);

		$css = $provider->getSearchTemplate()->getCss();

		$this->assertFileExists(__DIR__ . '/../../../css/' . $css . '.css');
	}
}
