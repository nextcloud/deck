<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Db;

use OCA\Deck\Command\BoardImport;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\Importer\Systems\DeckJsonService;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Server;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @group DB
 */
class ImportExportTest extends \Test\TestCase {

	private IDBConnection $connection;
	private const TEST_USER1 = 'test-share-user1';
	private const TEST_USER3 = 'test-share-user3';
	private const TEST_USER2 = 'test-share-user2';
	private const TEST_USER4 = 'test-share-user4';
	private const TEST_GROUP1 = 'test-share-group1';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($backend);
		Server::get(IUserManager::class)->registerBackend($backend);
		$backend->createUser(self::TEST_USER1, self::TEST_USER1);
		$backend->createUser(self::TEST_USER2, self::TEST_USER2);
		$backend->createUser(self::TEST_USER3, self::TEST_USER3);
		$backend->createUser(self::TEST_USER4, self::TEST_USER4);
		// create group
		$groupBackend = new \Test\Util\Group\Dummy();
		$groupBackend->createGroup(self::TEST_GROUP1);
		$groupBackend->createGroup('group');
		$groupBackend->createGroup('group1');
		$groupBackend->createGroup('group2');
		$groupBackend->createGroup('group3');
		$groupBackend->addToGroup(self::TEST_USER1, 'group');
		$groupBackend->addToGroup(self::TEST_USER2, 'group');
		$groupBackend->addToGroup(self::TEST_USER3, 'group');
		$groupBackend->addToGroup(self::TEST_USER2, 'group1');
		$groupBackend->addToGroup(self::TEST_USER3, 'group2');
		$groupBackend->addToGroup(self::TEST_USER4, 'group3');
		$groupBackend->addToGroup(self::TEST_USER2, self::TEST_GROUP1);
		Server::get(IGroupManager::class)->addBackend($groupBackend);
	}

	public function setUp(): void {
		parent::setUp();

		$this->connection = \OCP\Server::get(IDBConnection::class);
		$this->connection->beginTransaction();

	}

	public function testImportOcc() {
		$input = $this->createMock(InputInterface::class);
		$input->expects($this->any())
			->method('getOption')
			->willReturnCallback(function ($arg) {
				return match ($arg) {
					'system' => 'DeckJson',
					'data' => __DIR__ . '/../../data/deck.json',
					'config' => __DIR__ . '/../../data/config-trelloJson.json',
				};
			});
		$output = $this->createMock(OutputInterface::class);
		$importer = \OCP\Server::get(BoardImport::class);
		$application = new Application();
		$importer->setApplication($application);
		$importer->run($input, $output);

		$this->assertDatabase();
	}

	public function testImport() {
		$importer = \OCP\Server::get(BoardImportService::class);
		$deckJsonService = \OCP\Server::get(DeckJsonService::class);
		$deckJsonService->setImportService($importer);

		$importer->setSystem('DeckJson');
		$importer->setImportSystem($deckJsonService);
		$importer->setConfigInstance(json_decode(file_get_contents(__DIR__ . '/../../data/config-trelloJson.json')));
		$importer->setData(json_decode(file_get_contents(__DIR__ . '/../../data/deck.json')));
		$importer->import();

		$this->assertDatabase();
	}

	public function assertDatabase() {
		$boardMapper = \OCP\Server::get(BoardMapper::class);
		$boards = $boardMapper->findAllByOwner('admin');
		self::assertEquals('My test board', $boards[0]->getTitle());
		self::assertEquals('Shared board', $boards[1]->getTitle());
		self::assertEquals(2, count($boards));
	}

	public function tearDown(): void {
		if ($this->connection->inTransaction()) {
			$this->connection->rollBack();
		}
		parent::tearDown();
	}
}
