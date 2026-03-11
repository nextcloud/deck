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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Deck\Service\Importer\Systems;

use OCA\Deck\Service\Importer\BoardImportService;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group DB
 */
class DeckJsonServiceTest extends \Test\TestCase {
	private DeckJsonService $service;
	/** @var IUserManager|MockObject */
	private $userManager;
	public function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->service = new DeckJsonService(
			$this->userManager,
		);
	}

	public function testGetBoardWithNoName() {
		$this->expectExceptionMessage('Invalid name of board');
		$importService = $this->createMock(BoardImportService::class);
		$this->service->setImportService($importService);
		$this->service->getBoard();
	}

	public function testGetBoardWithSuccess() {
		$importService = $this->setUpImportService();

		$boards = $this->service->getBoards();
		$importService->setData($boards[0]);
		$actual = $this->service->getBoard();
		$this->assertEquals('My test board', $actual->getTitle());
		$this->assertEquals('admin', $actual->getOwner());
		$this->assertEquals('e0ed31', $actual->getColor());
	}

	public function testGetCards() {
		$importService = $this->setUpImportService();

		$boards = $this->service->getBoards();
		$importService->setData($boards[0]);

		$importService->getBoard()->setId(1);

		$this->service->getLabels();

		$stacks = $this->service->getStacks();
		$stackId = 1;
		foreach ($stacks as $code => $stack) {
			$stack->setId($stackId++);
			$this->service->updateStack($code, $stack);
		}

		$cards = $this->service->getCards();

		$this->assertCount(6, $cards);

		// Card 114 (title "1") has a done value set in the fixture
		$card114 = $cards[114];
		$this->assertEquals('1', $card114->getTitle());
		$this->assertInstanceOf(\DateTime::class, $card114->getDone());
		$this->assertEquals('2023-07-18T10:00:00+00:00', $card114->getDone()->format(\DateTime::ATOM));
		$this->assertEquals('2050-07-24T22:00:00+00:00', $card114->getDuedate()->format(\DateTime::ATOM));
		$this->assertFalse($card114->getArchived());
		$this->assertEquals('admin', $card114->getOwner());

		// Card 115 (title "2") has no done value in the fixture
		$card115 = $cards[115];
		$this->assertEquals('2', $card115->getTitle());
		$this->assertNull($card115->getDone());

		// Card 119 (title "6") — from stack B, no done value
		$card119 = $cards[119];
		$this->assertEquals('6', $card119->getTitle());
		$this->assertNull($card119->getDone());
		$this->assertEquals('# Test description' . "\n\n" . 'Hello world', $card119->getDescription());
	}

	private function setUpImportService(): BoardImportService {
		$importService = Server::get(BoardImportService::class);

		$data = json_decode(file_get_contents(__DIR__ . '/../../../../data/deck.json'));
		$importService->setData($data);

		$configInstance = json_decode(file_get_contents(__DIR__ . '/../../../../data/config-deckJson.json'));
		$importService->setConfigInstance($configInstance);

		$owner = $this->createMock(IUser::class);
		$owner
			->method('getUID')
			->willReturn('admin');
		$importService->setConfig('owner', $owner);

		$this->service->setImportService($importService);

		return $importService;
	}
}
