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

use OCA\Deck\Db\Assignment;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\Importer\ImportOptions;
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
	/** @var \OCA\Deck\Db\Stack[] */
	private array $importedStacks = [];
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
		$this->assertInstanceOf(\DateTime::class, $card114->getStartdate());
		$this->assertEquals('2023-07-10T08:00:00+00:00', $card114->getStartdate()->format(\DateTime::ATOM));
		$this->assertFalse($card114->getArchived());
		$this->assertEquals('admin', $card114->getOwner());

		// Card 115 (title "2") has a startdate but no done value in the fixture
		$card115 = $cards[115];
		$this->assertEquals('2', $card115->getTitle());
		$this->assertNull($card115->getDone());
		$this->assertInstanceOf(\DateTime::class, $card115->getStartdate());
		$this->assertEquals('2023-07-15T08:00:00+00:00', $card115->getStartdate()->format(\DateTime::ATOM));

		// Card 119 (title "6") — from stack B, no done or startdate value
		$card119 = $cards[119];
		$this->assertEquals('6', $card119->getTitle());
		$this->assertNull($card119->getDone());
		$this->assertNull($card119->getStartdate());
		$this->assertEquals('# Test description' . "\n\n" . 'Hello world', $card119->getDescription());
	}

	public function testGetStacksRestoresTheDoneColumn() {
		$importService = $this->setUpImportService('deck-complete.json');
		$importService->setData($this->service->getBoards()[0]);
		$importService->getBoard()->setId(1);

		$stacks = $this->service->getStacks();

		$this->assertFalse($stacks[310]->getIsDoneColumn());
		$this->assertTrue($stacks[311]->getIsDoneColumn());
	}

	public function testGetCardsKeepsArchivedAndDoneState() {
		$cards = $this->importCards();

		$this->assertCount(3, $cards);
		$this->assertFalse($cards[3100]->getArchived());
		$this->assertNull($cards[3100]->getDone());
		$this->assertTrue($cards[3101]->getArchived());
		$this->assertEquals('2023-07-18T10:00:00+00:00', $cards[3110]->getDone()->format(\DateTime::ATOM));
	}

	public function testArchivedCardsCanBeSkipped() {
		$cards = $this->importCards(new ImportOptions(importArchivedCards: false));

		$this->assertCount(2, $cards);
		$this->assertArrayNotHasKey(3101, $cards);
	}

	public function testDoneStateCanBeSkipped() {
		$cards = $this->importCards(new ImportOptions(importDoneState: false));

		$this->assertNull($cards[3110]->getDone());
		// the card itself is still imported, only its completion is dropped
		$this->assertEquals('Finished card', $cards[3110]->getTitle());
	}

	public function testDueDatesCanBeSkipped() {
		$cards = $this->importCards(new ImportOptions(importDueDates: false));

		$this->assertNull($cards[3100]->getDuedate());
		$this->assertNull($cards[3100]->getStartdate());
		// completion is a separate option and stays untouched
		$this->assertNotNull($cards[3110]->getDone());
	}

	public function testGetStacksOnlyRestoresTheFirstDoneColumn() {
		$board = $this->boardFixture();
		// a hand-crafted file could mark more than one list as the done column
		$board->stacks->{'310'}->isDoneColumn = true;
		$board->stacks->{'311'}->isDoneColumn = true;
		$importService = $this->prepareImport($board);

		$stacks = $this->importedStacks;

		$this->assertTrue($stacks[310]->getIsDoneColumn());
		$this->assertFalse($stacks[311]->getIsDoneColumn());
		$this->assertSame($importService->getBoard()->getId(), $stacks[310]->getBoardId());
	}

	public function testGetStacksWithoutADoneColumn() {
		$board = $this->boardFixture();
		unset($board->stacks->{'311'}->isDoneColumn);

		$this->prepareImport($board);
		$stacks = $this->importedStacks;

		$this->assertFalse($stacks[310]->getIsDoneColumn());
		$this->assertFalse($stacks[311]->getIsDoneColumn());
	}

	public function testGetCardsIgnoresUnparsableDates() {
		$board = $this->boardFixture();
		$card = $board->stacks->{'310'}->cards[0];
		$card->duedate = 'not a date';
		$card->startdate = '';
		$card->done = '2023-07-18 10:00:00';

		$cards = $this->importCardsOf($board);

		$this->assertNull($cards[3100]->getDuedate());
		$this->assertNull($cards[3100]->getStartdate());
		$this->assertNull($cards[3100]->getDone());
		// the card itself still has to be imported
		$this->assertEquals('Open card', $cards[3100]->getTitle());
	}

	public function testGetCardsWithoutAnyDateFields() {
		$board = $this->boardFixture();
		$card = $board->stacks->{'310'}->cards[0];
		unset($card->duedate, $card->startdate, $card->done, $card->archived);

		$cards = $this->importCardsOf($board);

		$this->assertNull($cards[3100]->getDuedate());
		$this->assertNull($cards[3100]->getStartdate());
		$this->assertNull($cards[3100]->getDone());
		$this->assertFalse($cards[3100]->getArchived());
	}

	/**
	 * The export writes `participant` as an object when it could resolve the
	 * user, group or circle and as a plain uid string when it could not.
	 *
	 * @dataProvider dataAssignedUsers
	 */
	public function testGetCardAssignmentsReadsBothParticipantFormats(array $assignedUser, string $expectedParticipant, int $expectedType) {
		$this->userManager->method('userExists')->willReturn(true);

		$board = $this->boardFixture();
		$board->stacks->{'310'}->cards[0]->assignedUsers = [json_decode(json_encode($assignedUser))];
		$this->importCardsOf($board);

		$assignments = $this->service->getCardAssignments();

		$this->assertCount(1, $assignments[3100]);
		$this->assertEquals($expectedParticipant, $assignments[3100][0]->getParticipant());
		$this->assertSame($expectedType, $assignments[3100][0]->getType());
	}

	public static function dataAssignedUsers(): array {
		return [
			'resolved user' => [
				['participant' => ['uid' => 'alice', 'primaryKey' => 'alice', 'type' => 0]],
				'alice',
				Assignment::TYPE_USER,
			],
			'group without a uid' => [
				['participant' => ['primaryKey' => 'devs', 'type' => 1]],
				'devs',
				Assignment::TYPE_GROUP,
			],
			'unresolved participant as plain string' => [
				['participant' => 'bob', 'type' => 0],
				'bob',
				Assignment::TYPE_USER,
			],
			'plain string without a type falls back to user' => [
				['participant' => 'bob'],
				'bob',
				Assignment::TYPE_USER,
			],
			'type on the assignment instead of the participant' => [
				['participant' => ['primaryKey' => 'circle1'], 'type' => 7],
				'circle1',
				Assignment::TYPE_CIRCLE,
			],
			'type given as string' => [
				['participant' => 'bob', 'type' => '1'],
				'bob',
				Assignment::TYPE_GROUP,
			],
		];
	}

	/**
	 * @dataProvider dataUnusableAssignedUsers
	 */
	public function testGetCardAssignmentsSkipsUnusableEntries(mixed $assignedUsers) {
		$this->userManager->method('userExists')->willReturn(true);

		$board = $this->boardFixture();
		$board->stacks->{'310'}->cards[0]->assignedUsers = json_decode(json_encode($assignedUsers));
		$this->importCardsOf($board);

		$this->assertEquals([], $this->service->getCardAssignments());
	}

	public static function dataUnusableAssignedUsers(): array {
		return [
			'no assignments' => [[]],
			'assignment without a participant' => [[['type' => 0]]],
			'participant object without an identifier' => [[['participant' => ['displayname' => 'Alice']]]],
			'participant explicitly null' => [[['participant' => null]]],
		];
	}

	public function testGetCardAssignmentsIgnoresCardsWithoutAssignedUsers() {
		$board = $this->boardFixture();
		unset($board->stacks->{'310'}->cards[0]->assignedUsers);
		$this->importCardsOf($board);

		$this->assertEquals([], $this->service->getCardAssignments());
	}

	public function testGetCardAssignmentsKeepsAssignmentsWithTheirCard() {
		$this->userManager->method('userExists')->willReturn(true);

		$board = $this->boardFixture();
		$board->stacks->{'310'}->cards[0]->assignedUsers = json_decode(json_encode([['participant' => 'alice', 'type' => 0]]));
		$board->stacks->{'311'}->cards[0]->assignedUsers = json_decode(json_encode([['participant' => 'bob', 'type' => 0]]));
		$cards = $this->importCardsOf($board);

		$assignments = $this->service->getCardAssignments();

		$this->assertEquals([3100, 3110], array_keys($assignments));
		$this->assertEquals($cards[3100]->getId(), $assignments[3100][0]->getCardId());
		$this->assertEquals($cards[3110]->getId(), $assignments[3110][0]->getCardId());
	}

	/**
	 * The first board of the complete fixture, as a fresh object that a test
	 * can modify without affecting the other tests.
	 */
	private function boardFixture(string $fixture = 'deck-complete.json'): object {
		$data = json_decode(file_get_contents(__DIR__ . '/../../../../data/' . $fixture));
		return array_values((array)$data->boards)[0];
	}

	/**
	 * Run the import steps up to (and including) the cards for a board that the
	 * test built itself.
	 *
	 * @return \OCA\Deck\Db\Card[]
	 */
	private function importCardsOf(object $board, ?ImportOptions $options = null): array {
		$this->prepareImport($board, $options);

		$cards = $this->service->getCards();
		$cardId = 1;
		foreach ($cards as $code => $card) {
			$card->setId($cardId++);
			$this->service->updateCard((string)$code, $card);
		}

		return $cards;
	}

	private function prepareImport(object $board, ?ImportOptions $options = null): BoardImportService {
		$importService = $this->setUpImportService();
		if ($options !== null) {
			$importService->setOptions($options);
		}
		$importService->setData($board);
		$importService->getBoard()->setId(1);

		$this->service->getLabels();
		// getStacks() collects the cards of every list, so it must run only once
		$this->importedStacks = $this->service->getStacks();
		$stackId = 1;
		foreach ($this->importedStacks as $code => $stack) {
			$stack->setId($stackId++);
			$this->service->updateStack((string)$code, $stack);
		}

		return $importService;
	}

	public function testGetCardsRestoresColourAndType() {
		$cards = $this->importCards();

		$this->assertEquals('ff0000', $cards[3100]->getColor());
		$this->assertEquals('text', $cards[3100]->getType());
		// a card without an explicit type falls back to the default
		$this->assertEquals('plain', $cards[3101]->getType());
		$this->assertNull($cards[3101]->getColor());
	}

	public function testGetCardDependenciesMapsSourceIdsToNewCards() {
		$cards = $this->importCards();

		$dependencies = $this->service->getCardDependencies();

		$this->assertEquals([$cards[3110]->getId()], $dependencies[$cards[3100]->getId()]);
		$this->assertEquals([$cards[3101]->getId()], $dependencies[$cards[3110]->getId()]);
		// 9999 is not part of this board, so it is dropped rather than guessed at
		$this->assertCount(2, $dependencies);
	}

	public function testGetCardDependenciesDropsCardsThatWereNotImported() {
		$cards = $this->importCards(new ImportOptions(importArchivedCards: false));

		$dependencies = $this->service->getCardDependencies();

		// 3101 is archived and was skipped, so the dependency on it goes too
		// instead of leaving a dangling id behind
		$this->assertArrayNotHasKey($cards[3110]->getId(), $dependencies);
		$this->assertEquals([$cards[3110]->getId()], $dependencies[$cards[3100]->getId()]);
	}

	/**
	 * @return \OCA\Deck\Db\Card[]
	 */
	private function importCards(?ImportOptions $options = null): array {
		$importService = $this->setUpImportService('deck-complete.json');
		if ($options !== null) {
			$importService->setOptions($options);
		}
		$importService->setData($this->service->getBoards()[0]);
		$importService->getBoard()->setId(1);

		$this->service->getLabels();
		$stackId = 1;
		foreach ($this->service->getStacks() as $code => $stack) {
			$stack->setId($stackId++);
			$this->service->updateStack($code, $stack);
		}

		$cards = $this->service->getCards();
		// the importer assigns ids as it inserts, mirror that so dependencies resolve
		$cardId = 1000;
		foreach ($cards as $code => $card) {
			$card->setId($cardId++);
			$this->service->updateCard($code, $card);
		}

		return $cards;
	}

	private function setUpImportService(string $fixture = 'deck.json'): BoardImportService {
		$importService = Server::get(BoardImportService::class);
		// shared instance, reset what a previous test may have left behind
		$importService->setOptions(new ImportOptions());

		$data = json_decode(file_get_contents(__DIR__ . '/../../../../data/' . $fixture));
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
