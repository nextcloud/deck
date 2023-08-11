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
use OCA\Deck\Command\UserExport;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\Importer\Systems\DeckJsonService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\StackService;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\Entity;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Server;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @group DB
 */
class ImportExportTest extends \Test\TestCase {

	private IDBConnection $connection;
	private const TEST_USER1 = 'test-export-user1';
	private const TEST_USER3 = 'test-export-user3';
	private const TEST_USER2 = 'test-export-user2';
	private const TEST_USER4 = 'test-export-user4';
	private const TEST_GROUP1 = 'test-export-group1';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($backend);
		Server::get(IUserManager::class)->registerBackend($backend);
		$backend->createUser('alice', 'alice');
		$backend->createUser('jane', 'jane');
		$backend->createUser('johndoe', 'johndoe');
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

		Server::get(PermissionService::class)->setUserId('admin');
	}

	public function setUp(): void {
		parent::setUp();

		$this->connection = \OCP\Server::get(IDBConnection::class);
		$this->cleanDb();
		$this->cleanDb(self::TEST_USER1);
	}

	public function testImportOcc() {
		$this->importFromFile(__DIR__ . '/../../data/deck.json');
		$this->assertDatabase();
	}

	/**
	 * This test runs an import, export and another import and
	 * assert that multiple attempts result in the same data structure
	 *
	 * In addition, it asserts that multiple import/export runs result in the same JSON
	 */
	public function testReimportOcc() {
		$this->importFromFile(__DIR__ . '/../../data/deck.json');
		$this->assertDatabase();

		$tmpExportFile = $this->exportToTemp();

		// Useful for double checking differences as there is no easy way to compare equal with skipping certain id keys, etag
		// self::assertEquals(file_get_contents(__DIR__ . '/../../data/deck.json'), $jsonOutput);
		self::assertEquals(
			self::writeArrayStructure(array: json_decode(file_get_contents(__DIR__ . '/../../data/deck.json'), true)),
			self::writeArrayStructure(array: json_decode(file_get_contents($tmpExportFile), true))
		);

		// cleanup test database
		$this->cleanDb();

		// Re-import from temporary file
		$this->importFromFile($tmpExportFile);
		$this->assertDatabase();

		$tmpExportFile2 = $this->exportToTemp();
		self::assertEquals(
			self::writeArrayStructure(array: json_decode(file_get_contents($tmpExportFile), true)),
			self::writeArrayStructure(array: json_decode(file_get_contents($tmpExportFile2), true))
		);
	}

	public static function writeArrayStructure(string $prefix = '', array $array = [], array $skipKeyList = ['id', 'boardId', 'cardId', 'stackId', 'ETag', 'permissions', 'shared']): string {
		$output = '';
		$arrayIsList = array_keys($array) === range(0, count($array) - 1);
		foreach ($array as $key => $value) {
			$tmpPrefix = $prefix;
			if (in_array($key, $skipKeyList)) {
				continue;
			}
			if (is_array($value)) {
				if ($key === 'participant' || $key === 'owner') {
					$output .= $tmpPrefix . $key . ' => ' . $value['primaryKey'] . PHP_EOL;
					continue;
				}
				$tmpPrefix .= (!$arrayIsList && !is_numeric($key) ? $key : '!!!') . ' => ';
				$output .= self::writeArrayStructure($tmpPrefix, $value, $skipKeyList);
			} else {
				$output .= $tmpPrefix . $key . ' => ' . $value . PHP_EOL;
			}
		}
		return $output;
	}

	public function cleanDb(string $owner = 'admin'): void {
		$this->connection->executeQuery('DELETE from oc_deck_boards;');
	}

	private function importFromFile(string $filePath): void {
		$input = $this->createMock(InputInterface::class);
		$input->expects($this->any())
			->method('getOption')
			->willReturnCallback(function ($arg) use ($filePath) {
				return match ($arg) {
					'system' => 'DeckJson',
					'data' => $filePath,
					'config' => __DIR__ . '/../../data/config-trelloJson.json',
				};
			});
		$output = $this->createMock(OutputInterface::class);
		$importer = self::getFreshService(BoardImport::class);
		$application = new Application();
		$importer->setApplication($application);
		$importer->run($input, $output);
	}

	/** Returns the path of a deck export json */
	private function exportToTemp(): string {
		\OCP\Server::get(BoardMapper::class)->flushCache();
		$application = new Application();
		$input = $this->createMock(InputInterface::class);
		$input->expects($this->any())
			->method('getArgument')
			->with('user-id')
			->willReturn('admin');
		$output = new BufferedOutput();
		$exporter = new UserExport(
			\OCP\Server::get(IAppManager::class),
			self::getFreshService(BoardMapper::class),
			self::getFreshService(BoardService::class),
			self::getFreshService(StackMapper::class),
			self::getFreshService(CardMapper::class),
			self::getFreshService(AssignmentMapper::class),
		);
		$exporter->setApplication($application);
		$exporter->run($input, $output);
		$jsonOutput = $output->fetch();
		json_decode($jsonOutput);
		self::assertTrue(json_last_error() === JSON_ERROR_NONE);
		$tmpExportFile = tempnam('/tmp', 'export');
		file_put_contents($tmpExportFile, $jsonOutput);
		return $tmpExportFile;
	}

	public function testImport() {
		$importer = self::getFreshService(BoardImportService::class);
		$deckJsonService = self::getFreshService(DeckJsonService::class);
		$deckJsonService->setImportService($importer);

		$importer->setSystem('DeckJson');
		$importer->setImportSystem($deckJsonService);
		$importer->setConfigInstance(json_decode(file_get_contents(__DIR__ . '/../../data/config-trelloJson.json')));
		$importer->setData(json_decode(file_get_contents(__DIR__ . '/../../data/deck.json')));
		$importer->import();

		$this->assertDatabase();
	}

	public function testImportAsOtherUser() {
		$importer = self::getFreshService(BoardImportService::class);
		$deckJsonService = self::getFreshService(DeckJsonService::class);
		$deckJsonService->setImportService($importer);

		$importer->setSystem('DeckJson');
		$importer->setImportSystem($deckJsonService);
		$importer->setConfigInstance((object)[
			'owner' => self::TEST_USER1
		]);
		$importer->setData(json_decode(file_get_contents(__DIR__ . '/../../data/deck.json')));
		$importer->import();

		$this->assertDatabase(self::TEST_USER1);
	}

	public function testImportWithRemap() {
		$importer = self::getFreshService(BoardImportService::class);
		$deckJsonService = self::getFreshService(DeckJsonService::class);
		$deckJsonService->setImportService($importer);

		$importer->setSystem('DeckJson');
		$importer->setImportSystem($deckJsonService);
		$importer->setConfigInstance((object)[
			'owner' => self::TEST_USER1,
			'uidRelation' => (object)[
				'alice' => self::TEST_USER2,
				'jane' => self::TEST_USER3,
			],
		]);
		$importer->setData(json_decode(file_get_contents(__DIR__ . '/../../data/deck.json')));
		$importer->import();

		$this->assertDatabase(self::TEST_USER1);
		$otherUserboards = self::getFreshService(BoardMapper::class)->findAllByUser(self::TEST_USER2);
		self::assertCount(1, $otherUserboards);
	}

	/**
	 * @template T
	 * @param class-string<T>|string $className
	 * @return T
	 */
	private function getFreshService(string $className): mixed {
		$fresh = \OC::$server->getRegisteredAppContainer('deck')->resolve($className);
		self::overwriteService($className, $fresh);
		return $fresh;
	}

	public function assertDatabase(string $owner = 'admin') {
		$permissionService = self::getFreshService(PermissionService::class);
		$permissionService->setUserId($owner);
		self::getFreshService(BoardService::class);
		self::getFreshService(CardService::class);
		$boardMapper = self::getFreshService(BoardMapper::class);
		$stackMapper = self::getFreshService(StackMapper::class);
		$cardMapper = self::getFreshService(CardMapper::class);

		$boards = $boardMapper->findAllByOwner($owner);
		$boardNames = array_map(fn ($board) => $board->getTitle(), $boards);
		self::assertEquals(2, count($boards));

		$board = $boards[0];
		self::assertEntity(Board::fromRow([
			'title' => 'My test board',
			'color' => 'e0ed31',
			'owner' => $owner,
			'lastModified' => 1689667796,
		]), $board);
		$boardService = $this->getFreshService(BoardService::class);
		$fullBoard = $boardService->find($board->getId(), true);
		self::assertEntityInArray(Label::fromParams([
			'title' => 'L2',
			'color' => '31CC7C',
		]), $fullBoard->getLabels(), true);


		$stacks = $stackMapper->findAll($board->getId());
		self::assertCount(3, $stacks);
		self::assertEntity(Stack::fromRow([
			'title' => 'A',
			'order' => 999,
			'boardId' => $boards[0]->getId(),
			'lastModified' => 1689667779,
		]), $stacks[0]);
		self::assertEntity(Stack::fromRow([
			'title' => 'B',
			'order' => 999,
			'boardId' => $boards[0]->getId(),
			'lastModified' => 1689667796,
		]), $stacks[1]);
		self::assertEntity(Stack::fromRow([
			'title' => 'C',
			'order' => 999,
			'boardId' => $boards[0]->getId(),
			'lastModified' => 0,
		]), $stacks[2]);

		$cards = $cardMapper->findAll($stacks[0]->getId());
		self::assertEntity(Card::fromRow([
			'title' => '1',
			'description' => '',
			'type' => 'plain',
			'lastModified' => 1689667779,
			'createdAt' => 1689667569,
			'owner' => $owner,
			'duedate' => new \DateTime('2050-07-24T22:00:00.000000+0000'),
			'order' => 999,
			'stackId' => $stacks[0]->getId(),
		]), $cards[0]);
		self::assertEntity(Card::fromRow([
			'title' => '2',
			'duedate' => new \DateTime('2050-07-24T22:00:00.000000+0000'),
		]), $cards[1], true);
		self::assertEntity(Card::fromParams([
			'title' => '3',
			'duedate' => null,
		]), $cards[2], true);

		$cards = $cardMapper->findAll($stacks[1]->getId());
		self::assertEntity(Card::fromParams([
			'title' => '6',
			'duedate' => null,
			'description' => "# Test description\n\nHello world",
		]), $cards[2], true);

		// Shared board
		$sharedBoard = $boards[1];
		self::assertEntity(Board::fromRow([
			'title' => 'Shared board',
			'color' => '30b6d8',
			'owner' => $owner,
		]), $sharedBoard, true);

		$stackService = self::getFreshService(StackService::class);
		$stacks = $stackService->findAll($board->getId());
		self::assertEntityInArray(Label::fromParams([
			'title' => 'L2',
			'color' => '31CC7C',
		]), $stacks[0]->getCards()[0]->getLabels(), true);
		self::assertEntity(Label::fromParams([
			'title' => 'L2',
			'color' => '31CC7C',
		]), $stacks[0]->getCards()[0]->getLabels()[0], true);

		$stacks = $stackMapper->findAll($sharedBoard->getId());
		self::assertCount(3, $stacks);
	}

	public static function assertEntityInArray(Entity $expected, array $array, bool $checkProperties): void {
		$exists = null;
		foreach ($array as $entity) {
			try {
				self::assertEntity($expected, $entity, $checkProperties);
				$exists = $entity;
			} catch (ExpectationFailedException $e) {
			}
		}
		if ($exists) {
			self::assertEntity($expected, $exists, $checkProperties);
		} else {
			// THis is hard to debug if it fails as the actual diff is not returned but hidden in the above exception
			self::assertEquals($expected, $exists);
		}
	}

	public static function assertEntity(Entity $expected, Entity $actual, bool $checkProperties = false): void {
		if ($checkProperties === true) {
			$e = clone $expected;
			$a = clone $actual;
			foreach ($e->getUpdatedFields() as $property => $updated) {
				$expectedValue = call_user_func([$e, 'get' . ucfirst($property)]);
				$actualValue = call_user_func([$a, 'get' . ucfirst($property)]);
				self::assertEquals(
					$expectedValue,
					$actualValue
				);
			}
		} else {
			$e = clone $expected;
			$e->setId(null);
			$a = clone $actual;
			$a->setId(null);
			$e->resetUpdatedFields();
			$a->resetUpdatedFields();
			self::assertEquals($e, $a);
		}
	}

	public function tearDown(): void {
		$this->cleanDb();
		$this->cleanDb(self::TEST_USER1);
		parent::tearDown();
	}
}
