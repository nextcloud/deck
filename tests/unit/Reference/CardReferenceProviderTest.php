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

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
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

	public static function dataUrl(): array {
		return [
			['https://nextcloud.com', null],
			['https://localhost/apps/deck/#!/board/2/card/11', 11],
			['https://localhost/index.php/apps/deck/#!/board/2/card/11', 11],
			['https://localhost/apps/deck/#/board/2/card/11', 11],
			['https://localhost/index.php/apps/deck/#/board/2/card/11', 11],
			['https://localhost/apps/deck/board/2/card/11', 11],
			['https://localhost/index.php/apps/deck/board/2/card/11', 11],
			['https://localhost/apps/deck/card/11', 11],
			['https://localhost/index.php/apps/deck/card/11', 11],
		];
	}

	/**
	 * @dataProvider dataUrl
	 */
	public function testUrl($url, $id) {
		$this->urlGenerator->expects($this->any())
			->method('getAbsoluteURL')
			->willReturnCallback(function ($path) {
				return 'https://localhost/' . ltrim($path, '/');
			});
		$matchExpect = $id !== null;
		self::assertEquals($matchExpect, $this->provider->matchReference($url));

		$card = Card::fromRow([
			'id' => $id,
			'stackId' => 1234,
		]);
		$stack = Stack::fromRow([
			'boardId' => 9876,
		]);
		$board = Board::fromRow([
			'id' => 9876,
		]);

		$this->cardService->method('find')->with($id)->willReturn($card);
		$this->stackService->method('find')->with(1234)->willReturn($stack);
		$this->boardService->method('find')->with(9876)->willReturn($board);

		$reference = $this->provider->resolveReference($url);

		if ($id !== null) {
			self::assertEquals($id, $reference->jsonSerialize()['richObject']['card']['id']);
			self::assertEquals(9876, $reference->jsonSerialize()['richObject']['board']['id']);

		} else {
			self::assertNull($reference);
		}
	}
}
