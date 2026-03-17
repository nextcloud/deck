<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Tests\Unit;

use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\CommentService;
use OCA\Deck\Service\FilesAppService;
use OCA\Deck\Service\LabelService;
use OCA\Deck\Service\StackService;
use OCA\Deck\UserMigration\DeckMigrator;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class DeckMigratorTest extends TestCase {
	private IRootFolder $rootFolder;
	private Folder $userFolder;
	private IL10N $l10n;
	private DeckMigrator $deckMigrator;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userFolder = $this->createMock(Folder::class);
		$this->userFolder
			->method('getPath')
			->willReturn('/tmp/testuser');
		$this->rootFolder
			->method('getUserFolder')
			->willReturn($this->userFolder);

		$mockBoardService = $this->createMock(BoardService::class);
		$mockBoardMapper = $this->createMock(BoardMapper::class);
		$mockConnection = $this->getMockBuilder('stdClass')
			->addMethods(['beginTransaction', 'commit', 'rollBack'])
			->getMock();
		$mockConnection->method('beginTransaction')->willReturn(null);
		$mockConnection->method('commit')->willReturn(null);
		$mockConnection->method('rollBack')->willReturn(null);
		$mockBoardMapper->method('getDbConnection')->willReturn($mockConnection);
		$mockAclMapper = $this->createMock(AclMapper::class);
		$mockLabelMapper = $this->createMock(LabelMapper::class);
		$mockStackMapper = $this->createMock(StackMapper::class);
		$mockCardMapper = $this->createMock(CardMapper::class);
		$mockAssignmentMapper = $this->createMock(AssignmentMapper::class);
		$mockCommentService = $this->createMock(CommentService::class);
		$mockFilesAppService = $this->createMock(FilesAppService::class);
		$mockConfig = $this->createMock(IConfig::class);
		$mockStackService = $this->createMock(StackService::class);
		$mockLabelService = $this->createMock(LabelService::class);
		$mockCardService = $this->createMock(CardService::class);
		$mockAssignmentService = $this->createMock(AssignmentService::class);
		$mockLogger = $this->createMock(LoggerInterface::class);

		$this->deckMigrator = new DeckMigrator(
			$this->l10n,
			$this->rootFolder,
			$mockBoardService,
			$mockBoardMapper,
			$mockAclMapper,
			$mockLabelMapper,
			$mockStackMapper,
			$mockCardMapper,
			$mockAssignmentMapper,
			$mockCommentService,
			$mockFilesAppService,
			$mockConfig,
			$mockStackService,
			$mockLabelService,
			$mockCardService,
			$mockAssignmentService,
			$mockLogger
		);
	}

	public function testGetEstimatedExportSize(): void {
		$user = $this->createMock(IUser::class);
		$this->assertIsNumeric($this->deckMigrator->getEstimatedExportSize($user));
	}

	public function testExport(): void {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getUID')->willReturn('testuser');
		$exportDestination = $this->createMock(IExportDestination::class);
		$output = $this->createMock(OutputInterface::class);

		$this->deckMigrator->export($user, $exportDestination, $output);
	}

	public function testImport(): void {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getUID')->willReturn('testuser');
		$importSource = $this->createMock(IImportSource::class);
		$output = $this->createMock(OutputInterface::class);

		$importSource
			->method('getMigratorVersion')
			->with('decks')
			->willReturn(1);

		$importSource
			->method('getFileContents')
			->willReturnMap([
				['boards.json', '[]'],
				['board_labels.json', '[]'],
				['board_acl.json', '[]'],
				['board_stacks.json', '[]'],
				['stack_cards.json', '[]'],
				['card_labels.json', '[]'],
				['card_assigned.json', '[]'],
				['card_comments.json', '[]'],
				['card_attachments.json', '[]'],
			]);

		$this->deckMigrator->import($user, $importSource, $output);
	}
}
