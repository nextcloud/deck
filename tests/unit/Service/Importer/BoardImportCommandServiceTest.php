<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer;

use OC\Comments\Comment;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\AttachmentService;
use OCA\Deck\Service\Importer\Systems\DeckJsonService;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * `occ deck:import` runs its own copy of the import step list, so the option
 * gating has to be verified here as well and not only on BoardImportService.
 */
class BoardImportCommandServiceTest extends \Test\TestCase {
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var AclMapper|MockObject */
	private $aclMapper;
	/** @var LabelMapper|MockObject */
	private $labelMapper;
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var AssignmentMapper|MockObject */
	private $assignmentMapper;
	/** @var ICommentsManager|MockObject */
	private $commentsManager;
	/** @var DeckJsonService|MockObject */
	private $importSystem;
	private BufferedOutput $output;
	private BoardImportCommandService $service;

	public function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);

		$this->service = new BoardImportCommandService(
			$this->userManager,
			$this->boardMapper,
			$this->aclMapper,
			$this->labelMapper,
			$this->stackMapper,
			$this->assignmentMapper,
			$this->createMock(AttachmentMapper::class),
			$this->cardMapper,
			$this->commentsManager,
			$this->createMock(IEventDispatcher::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(AttachmentService::class),
		);

		$input = $this->createMock(InputInterface::class);
		$input->method('getOption')->willReturnCallback(static fn (string $name) => match ($name) {
			'system' => 'DeckJson',
			'config' => null,
			default => null,
		});
		$this->output = new BufferedOutput();
		$this->service->setInput($input);
		$this->service->setOutput($this->output);

		$this->importSystem = $this->createMock(DeckJsonService::class);
		$this->service->setImportSystem($this->importSystem);

		$this->stubFullImport();
	}

	private function stubFullImport(): void {
		$this->userManager->method('userExists')->willReturn(true);

		$board = new Board();
		$board->setTitle('Board A');
		$board->setOwner('admin');

		$this->importSystem->method('getBoards')->willReturn([(object)['title' => 'Board A']]);
		$this->importSystem->method('getBoard')->willReturn($board);
		$this->importSystem->method('getAclList')->willReturn([new Acl()]);
		$this->importSystem->method('getLabels')->willReturn([new Label()]);
		$this->importSystem->method('getStacks')->willReturn([new Stack()]);
		$this->importSystem->method('getCards')->willReturn([new Card()]);
		$this->importSystem->method('getCardLabelAssignment')->willReturn([1 => [2]]);
		$this->importSystem->method('getComments')->willReturn(['1' => [new Comment()]]);

		$assignment = new Assignment();
		$assignment->setId(1);
		$this->importSystem->method('getCardAssignments')->willReturn(['1' => [$assignment]]);
		$this->assignmentMapper->method('insert')->willReturn($assignment);
	}

	public function testImportRunsEveryStepByDefault() {
		$this->boardMapper->expects($this->once())->method('insert');
		$this->aclMapper->expects($this->once())->method('insert');
		$this->labelMapper->expects($this->once())->method('insert');
		$this->stackMapper->expects($this->once())->method('insert');
		$this->cardMapper->expects($this->once())->method('insert');
		$this->cardMapper->expects($this->once())->method('assignLabel');
		$this->importSystem->expects($this->once())->method('importAttachments');
		$this->commentsManager->expects($this->once())->method('save');
		$this->assignmentMapper->expects($this->once())->method('insert');

		$this->service->import();

		// the formatter strips the <info>/<error> tags, so the text is matched instead
		$printed = $this->output->fetch();
		self::assertStringContainsString('Finished board import of "Board A"', $printed);
		self::assertStringNotContainsString('Import failed', $printed);
	}

	public function testImportWithoutCardsSkipsEverythingBelowTheCardLevel() {
		$this->service->setOptions(new ImportOptions(importCards: false));

		$this->stackMapper->expects($this->once())->method('insert');
		$this->cardMapper->expects($this->never())->method('insert');
		$this->cardMapper->expects($this->never())->method('assignLabel');
		$this->importSystem->expects($this->never())->method('importAttachments');
		$this->commentsManager->expects($this->never())->method('save');
		$this->assignmentMapper->expects($this->never())->method('insert');

		$this->service->import();

		$printed = $this->output->fetch();
		self::assertStringContainsString('Importing stacks...', $printed);
		self::assertStringNotContainsString('Importing cards...', $printed);
	}

	public function testImportWithoutSharingSkipsTheAcl() {
		$this->service->setOptions(new ImportOptions(importSharing: false));

		$this->aclMapper->expects($this->never())->method('insert');
		$this->boardMapper->expects($this->once())->method('insert');

		$this->service->import();

		self::assertStringNotContainsString('Assign users to board...', $this->output->fetch());
	}

	public function testImportWithoutLabelsAlsoSkipsTheirCardAssignment() {
		$this->service->setOptions(new ImportOptions(importLabels: false));

		$this->labelMapper->expects($this->never())->method('insert');
		$this->cardMapper->expects($this->never())->method('assignLabel');
		$this->cardMapper->expects($this->once())->method('insert');

		$this->service->import();
	}

	public function testImportWithoutAttachments() {
		$this->service->setOptions(new ImportOptions(importAttachments: false));

		$this->importSystem->expects($this->never())->method('importAttachments');
		$this->cardMapper->expects($this->once())->method('insert');

		$this->service->import();
	}

	public function testImportWithoutComments() {
		$this->service->setOptions(new ImportOptions(importComments: false));

		$this->commentsManager->expects($this->never())->method('save');
		$this->cardMapper->expects($this->once())->method('insert');

		$this->service->import();
	}

	public function testImportWithoutAssignments() {
		$this->service->setOptions(new ImportOptions(importAssignments: false));

		$this->assignmentMapper->expects($this->never())->method('insert');
		$this->cardMapper->expects($this->once())->method('insert');

		$this->service->import();
	}

	public function testImportReportsAFailingBoardWithoutAbortingTheCommand() {
		$this->boardMapper->method('insert')->willThrowException(new \RuntimeException('database is gone'));

		$this->service->import();

		self::assertStringContainsString('Import failed for board Board A: database is gone', $this->output->fetch());
	}
}
