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
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group DB
 */
class DeckJsonServiceTest extends \Test\TestCase {
	private DeckJsonService $service;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var IL10N */
	private $l10n;
	public function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->service = new DeckJsonService(
			$this->userManager,
			$this->urlGenerator,
			$this->l10n
		);
	}

	public function testGetBoardWithNoName() {
		$this->expectExceptionMessage('Invalid name of board');
		$importService = $this->createMock(BoardImportService::class);
		$this->service->setImportService($importService);
		$this->service->getBoard();
	}

	public function testGetBoardWithSuccess() {
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

		$boards = $this->service->getBoards();
		$importService->setData($boards[0]);
		$actual = $this->service->getBoard();
		$this->assertEquals('My test board', $actual->getTitle());
		$this->assertEquals('admin', $actual->getOwner());
		$this->assertEquals('e0ed31', $actual->getColor());
	}
}
