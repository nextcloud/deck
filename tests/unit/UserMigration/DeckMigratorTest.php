<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\UserMigration;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardExportService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\PermissionService;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class DeckMigratorTest extends TestCase {
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var BoardExportService|MockObject */
	private $boardExportService;
	/** @var BoardService|MockObject */
	private $boardService;
	/** @var BoardImportService|MockObject */
	private $boardImportService;
	/** @var PermissionService|MockObject */
	private $permissionService;
	private DeckMigrator $migrator;

	public function setUp(): void {
		parent::setUp();

		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->boardExportService = $this->createMock(BoardExportService::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->boardImportService = $this->createMock(BoardImportService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$this->migrator = new DeckMigrator(
			$this->createMock(IL10N::class),
			$this->boardMapper,
			$this->boardExportService,
			$this->boardService,
			$this->boardImportService,
			$this->permissionService,
		);
	}

	public function testExportWritesBoardsJson(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$board = new Board();
		$board->setId(42);
		$board->setTitle('Board A');

		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->with('admin')
			->willReturn([$board]);
		$this->boardService->expects($this->once())
			->method('setUserId')
			->with('admin');
		$this->permissionService->expects($this->once())
			->method('setUserId')
			->with('admin');
		$this->boardExportService->expects($this->once())
			->method('exportBoards')
			->with([$board])
			->willReturn([42 => ['id' => 42, 'title' => 'Board A', 'stacks' => []]]);

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->once())
			->method('addFileContents')
			->with(
				'boards.json',
				$this->callback(static function (string $json): bool {
					$decoded = json_decode($json, true);
					// the board list has to be a JSON array, not an object keyed by id
					return is_array($decoded)
						&& isset($decoded['boards'])
						&& array_keys($decoded['boards']) === [0]
						&& $decoded['boards'][0]['title'] === 'Board A';
				})
			);

		$this->migrator->export($user, $destination, $this->createMock(OutputInterface::class));
	}

	public function testExportWritesEmptyBoardListWhenNothingToExport(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->with('admin')
			->willReturn([]);
		$this->boardExportService->expects($this->once())
			->method('exportBoards')
			->willReturn([]);

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->once())
			->method('addFileContents')
			->with(
				'boards.json',
				$this->callback(static function (string $json): bool {
					$decoded = json_decode($json, true);
					return is_array($decoded) && $decoded['boards'] === [];
				})
			);

		$this->migrator->export($user, $destination, $this->createMock(OutputInterface::class));
	}

	public function testExportWrapsFailuresIntoAMigratorException(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$this->boardMapper->method('findAllByUser')->willReturn([]);
		$this->boardExportService->method('exportBoards')
			->willThrowException(new \RuntimeException('database is gone'));

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->never())->method('addFileContents');

		$this->expectException(DeckMigratorException::class);
		$this->expectExceptionMessage('database is gone');

		$this->migrator->export($user, $destination, $this->createMock(OutputInterface::class));
	}

	public function testExportKeepsTheCompleteBoardPayload(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$board = new Board();
		$board->setId(42);

		$this->boardMapper->method('findAllByUser')->willReturn([$board]);
		$this->boardExportService->method('exportBoards')->willReturn([
			42 => [
				'id' => 42,
				'title' => 'Board A',
				'stacks' => [[
					'id' => 1,
					'cards' => [[
						'id' => 7,
						'archived' => true,
						'done' => '2023-07-18T10:00:00+00:00',
						'comments' => [['id' => '1', 'message' => 'Hi']],
						'attachments' => [['type' => 'file', 'data' => 'a.txt', 'contentBase64' => 'eA==']],
					]],
				]],
			],
		]);

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->once())
			->method('addFileContents')
			->with('boards.json', $this->callback(static function (string $json): bool {
				$card = json_decode($json, true)['boards'][0]['stacks'][0]['cards'][0];
				return $card['archived'] === true
					&& $card['done'] === '2023-07-18T10:00:00+00:00'
					&& $card['comments'][0]['message'] === 'Hi'
					&& $card['attachments'][0]['contentBase64'] === 'eA==';
			}));

		$this->migrator->export($user, $destination, $this->createMock(OutputInterface::class));
	}

	public function testImportSkipsWhenNoVersion(): void {
		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->with('deck')->willReturn(null);

		$this->boardImportService->expects($this->never())->method('import');

		$this->migrator->import(
			$this->createMock(IUser::class),
			$source,
			$this->createMock(OutputInterface::class),
		);
	}

	public function testImportSkipsWhenCreatingBoardsIsNotAllowed(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->willReturn(1);

		$this->permissionService->method('canCreate')->willReturn(false);
		$this->boardImportService->expects($this->never())->method('import');

		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->once())
			->method('writeln')
			->with('Deck import failed: user is not allowed to create boards.');

		$this->migrator->import($user, $source, $output);
	}

	public function testImportOfAnEmptyBoardList(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->willReturn(1);
		$source->method('getFileContents')->willReturn('{"boards":[]}');

		$this->permissionService->method('canCreate')->willReturn(true);
		$this->boardImportService->expects($this->never())->method('setData');
		$this->boardImportService->expects($this->never())->method('import');

		$this->migrator->import($user, $source, $this->createMock(OutputInterface::class));
	}

	public function testImportOfBrokenJson(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->willReturn(1);
		$source->method('getFileContents')->willReturn('this is not json');

		$this->permissionService->method('canCreate')->willReturn(true);
		$this->boardImportService->expects($this->never())->method('import');

		$this->expectException(DeckMigratorException::class);

		$this->migrator->import($user, $source, $this->createMock(OutputInterface::class));
	}

	public function testImportWrapsFailuresIntoAMigratorException(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->willReturn(1);
		$source->method('getFileContents')->willReturn('{"boards":[{"id":1,"title":"Board A","stacks":[]}]}');

		$this->permissionService->method('canCreate')->willReturn(true);
		$this->boardImportService->method('import')->willThrowException(new \RuntimeException('import failed'));

		$this->expectException(DeckMigratorException::class);
		$this->expectExceptionMessage('import failed');

		$this->migrator->import($user, $source, $this->createMock(OutputInterface::class));
	}

	public function testImportConfiguresServiceAndImports(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->with('deck')->willReturn(1);
		$source->method('getFileContents')->with('boards.json')->willReturn('{"boards":[{"id":1,"title":"Board A","stacks":[]}]}');

		$this->permissionService->expects($this->once())
			->method('setUserId')
			->with('alice');
		$this->permissionService->expects($this->once())
			->method('canCreate')
			->willReturn(true);

		$this->boardImportService->expects($this->once())->method('setSystem')->with('DeckJson');
		$this->boardImportService->expects($this->once())
			->method('setConfigInstance')
			->with($this->callback(static function (\stdClass $config): bool {
				return isset($config->owner, $config->uidRelation) && $config->owner === 'alice';
			}));
		$this->boardImportService->expects($this->once())
			->method('setData')
			->with($this->callback(static function (\stdClass $data): bool {
				return isset($data->boards) && is_array($data->boards) && count($data->boards) === 1;
			}));
		$this->boardImportService->expects($this->once())->method('import');

		$this->migrator->import($user, $source, $this->createMock(OutputInterface::class));
	}
}
