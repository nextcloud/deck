<?php
/**
 * @copyright Copyright (c) 2023 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Reference;

use OCA\Deck\Reference\CardReferenceProvider;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\StackService;
use OCP\IL10N;
use OCP\IURLGenerator;
use Test\TestCase;

class CardReferenceProviderTest extends TestCase {
	public function setUp() : void {
		parent::setUp();

		$this->cardService = $this->createMock(CardService::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->stackService = $this->createMock(StackService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userId = null;

		$this->provider = new CardReferenceProvider(
			$this->cardService,
			$this->boardService,
			$this->stackService,
			$this->urlGenerator,
			$this->l10n,
			$this->userId,
		);
	}

	public function testUrl() {
		$this->urlGenerator->expects($this->any())
			->method('getAbsoluteURL')
			->willReturnCallback(function ($path) {
				return 'https://localhost/' . ltrim($path, '/');
			});
		self::assertFalse($this->provider->matchReference('https://nextcloud.com'));
		self::assertTrue($this->provider->matchReference('https://localhost/apps/deck/#/board/2/card/11'));
		self::assertTrue($this->provider->matchReference('https://localhost/index.php/apps/deck/#/board/2/card/11'));
		self::assertTrue($this->provider->matchReference('https://localhost/apps/deck/card/11'));
		self::assertTrue($this->provider->matchReference('https://localhost/index.php/apps/deck/card/11'));
	}
}
