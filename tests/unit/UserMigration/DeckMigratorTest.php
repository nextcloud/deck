<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\UserMigration;

use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\ShareFileAttachmentExportService;
use OCP\Comments\ICommentsManager;
use OCP\Files\IAppData;
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
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var LabelMapper|MockObject */
	private $labelMapper;
	/** @var AclMapper|MockObject */
	private $aclMapper;
	/** @var AssignmentMapper|MockObject */
	private $assignmentMapper;
	/** @var AttachmentMapper|MockObject */
	private $attachmentMapper;
	/** @var ICommentsManager|MockObject */
	private $commentsManager;
	/** @var IAppData|MockObject */
	private $appData;
	/** @var ShareFileAttachmentExportService|MockObject */
	private $shareFileAttachmentExportService;
	/** @var BoardService|MockObject */
	private $boardService;
	/** @var BoardImportService|MockObject */
	private $boardImportService;
	private DeckMigrator $migrator;

	public function setUp(): void {
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->shareFileAttachmentExportService = $this->createMock(ShareFileAttachmentExportService::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->boardImportService = $this->createMock(BoardImportService::class);

		$this->migrator = new DeckMigrator(
			$this->createMock(IL10N::class),
			$this->boardMapper,
			$this->stackMapper,
			$this->cardMapper,
			$this->labelMapper,
			$this->aclMapper,
			$this->assignmentMapper,
			$this->attachmentMapper,
			$this->commentsManager,
			$this->appData,
			$this->shareFileAttachmentExportService,
			$this->boardService,
			$this->boardImportService,
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
		$this->labelMapper->expects($this->once())->method('findAll')->with(42)->willReturn([]);
		$this->aclMapper->expects($this->once())->method('findAll')->with(42)->willReturn([]);
		$this->stackMapper->expects($this->once())->method('findAll')->with(42)->willReturn([]);

		$destination = $this->createMock(IExportDestination::class);
		$destination->expects($this->once())
			->method('addFileContents')
			->with(
				'boards.json',
				$this->callback(static function (string $json): bool {
					$decoded = json_decode($json, true);
					return is_array($decoded) && isset($decoded['boards']) && count($decoded['boards']) === 1;
				})
			);

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

	public function testImportConfiguresServiceAndImports(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->with('deck')->willReturn(1);
		$source->method('getFileContents')->with('boards.json')->willReturn('{"boards":[{"id":1,"title":"Board A","stacks":[]}]}');

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
