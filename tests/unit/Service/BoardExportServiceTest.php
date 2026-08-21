<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\NoPermissionException;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class BoardExportServiceTest extends \Test\TestCase {
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var LabelMapper|MockObject */
	private $labelMapper;
	/** @var AssignmentMapper|MockObject */
	private $assignmentMapper;
	/** @var AttachmentMapper|MockObject */
	private $attachmentMapper;
	/** @var ICommentsManager|MockObject */
	private $commentsManager;
	/** @var ShareFileAttachmentExportService|MockObject */
	private $shareFileAttachmentExportService;
	/** @var FileService|MockObject */
	private $fileService;
	/** @var PermissionService|MockObject */
	private $permissionService;
	/** @var LoggerInterface|MockObject */
	private $logger;
	private BoardExportService $service;

	public function setUp(): void {
		parent::setUp();

		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->shareFileAttachmentExportService = $this->createMock(ShareFileAttachmentExportService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new BoardExportService(
			$this->boardMapper,
			$this->stackMapper,
			$this->cardMapper,
			$this->labelMapper,
			$this->assignmentMapper,
			$this->attachmentMapper,
			$this->commentsManager,
			$this->shareFileAttachmentExportService,
			$this->fileService,
			$this->permissionService,
			$this->logger,
		);
	}

	private function board(int $id = 1): Board {
		$board = new Board();
		$board->setId($id);
		$board->setTitle('Board ' . $id);
		$board->setOwner('admin');
		return $board;
	}

	private function stack(int $id, int $boardId, bool $isDoneColumn = false): Stack {
		$stack = new Stack();
		$stack->setId($id);
		$stack->setBoardId($boardId);
		$stack->setTitle('Stack ' . $id);
		$stack->setOrder(0);
		$stack->setIsDoneColumn($isDoneColumn);
		return $stack;
	}

	private function card(int $id, int $stackId, bool $archived = false): Card {
		$card = new Card();
		$card->setId($id);
		$card->setStackId($stackId);
		$card->setTitle('Card ' . $id);
		$card->setOrder(0);
		$card->setArchived($archived);
		return $card;
	}

	private function comment(string $id, string $message = 'Hello'): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn($id);
		$comment->method('getParentId')->willReturn('0');
		$comment->method('getActorType')->willReturn('users');
		$comment->method('getActorId')->willReturn('alice');
		$comment->method('getMessage')->willReturn($message);
		$comment->method('getCreationDateTime')->willReturn(new \DateTime('2023-07-18T10:00:00+00:00'));
		$comment->method('getObjectType')->willReturn('deckCard');
		$comment->method('getObjectId')->willReturn('100');
		$comment->method('getVerb')->willReturn('comment');
		return $comment;
	}

	private function attachment(int $id, int $cardId, string $type = 'deck_file', string $data = 'notes.txt'): Attachment {
		$attachment = new Attachment();
		$attachment->setId($id);
		$attachment->setCardId($cardId);
		$attachment->setType($type);
		$attachment->setData($data);
		$attachment->setCreatedBy('admin');
		$attachment->setCreatedAt(1689667796);
		$attachment->setLastModified(1689667800);
		return $attachment;
	}

	public function testExportIncludesCardDependencies(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10), $this->card(101, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->cardMapper->expects($this->once())
			->method('findDependenciesForCards')
			->with([100, 101])
			->willReturn([100 => [101, 102]]);
		$this->expectNoExtras();

		$cards = $this->service->exportBoard(1)['stacks'][0]['cards'];

		self::assertSame([101, 102], $cards[0]['dependentCards']);
		// a card without dependencies gets an empty list, like the REST API returns
		self::assertSame([], $cards[1]['dependentCards']);
	}

	private function expectNoExtras(): void {
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->attachmentMapper->method('findAllForCards')->willReturn([]);
		$this->shareFileAttachmentExportService->method('exportAttachmentsForCards')->willReturn([]);
		$this->shareFileAttachmentExportService->method('countAttachmentsForCards')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);
	}

	public function testSetUserIdIsForwardedToThePermissionService(): void {
		$this->permissionService->expects($this->once())
			->method('setUserId')
			->with('alice');

		$this->service->setUserId('alice');
	}

	public function testExportRequiresReadPermission(): void {
		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 42, Acl::PERMISSION_READ)
			->willReturn(true);
		$this->boardMapper->method('find')->with(42, true, true)->willReturn($this->board(42));
		$this->stackMapper->method('findAll')->willReturn([]);
		$this->expectNoExtras();

		$this->service->exportBoard(42);
	}

	public function testExportWithoutPermissionDoesNotReadTheBoard(): void {
		$this->permissionService->method('checkPermission')
			->willThrowException(new NoPermissionException('nope'));
		$this->boardMapper->expects($this->never())->method('find');

		$this->expectException(NoPermissionException::class);
		$this->service->exportBoard(42);
	}

	public function testExportResolvesOwnerAndAcl(): void {
		$board = $this->board();
		$acl = new Acl();
		$acl->setId(5);
		$acl->setBoardId(1);
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant('alice');
		$board->setAcl([$acl]);

		$this->boardMapper->method('find')->willReturn($board);
		$this->boardMapper->expects($this->once())->method('mapOwner')->with($board);
		$this->boardMapper->expects($this->once())->method('mapAcl');
		$this->stackMapper->method('findAll')->willReturn([]);
		$this->expectNoExtras();

		$data = $this->service->exportBoard(1);

		self::assertCount(1, $data['acl']);
	}

	public function testExportDropsRequestSpecificBoardProperties(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([]);
		$this->expectNoExtras();

		$data = $this->service->exportBoard(1);

		// both describe the requesting user/session, not the board itself
		self::assertArrayNotHasKey('permissions', $data);
		self::assertArrayNotHasKey('activeSessions', $data);
		self::assertSame(1, $data['id']);
		self::assertSame('Board 1', $data['title']);
	}

	public function testExportOfBoardWithoutStacksQueriesNoCards(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([]);
		$this->cardMapper->expects($this->never())->method('findAllForStacks');
		$this->cardMapper->expects($this->never())->method('findAllArchivedForStacks');
		$this->expectNoExtras();

		$data = $this->service->exportBoard(1);

		self::assertSame([], $data['stacks']);
	}

	public function testExportOfStacksWithoutCards(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1), $this->stack(11, 1)]);
		$this->cardMapper->method('findAllForStacks')->with([10, 11])->willReturn([]);
		$this->cardMapper->method('findAllArchivedForStacks')->with([10, 11])->willReturn([]);
		$this->labelMapper->expects($this->never())->method('findAssignedLabelsForCards');
		$this->assignmentMapper->expects($this->never())->method('findIn');
		$this->attachmentMapper->expects($this->never())->method('findAllForCards');

		$data = $this->service->exportBoard(1);

		self::assertCount(2, $data['stacks']);
		self::assertSame([], $data['stacks'][0]['cards']);
		self::assertSame([], $data['stacks'][1]['cards']);
	}

	public function testExportKeepsCardsWithTheirStack(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1), $this->stack(11, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([
			10 => [$this->card(100, 10)],
			11 => [$this->card(110, 11), $this->card(111, 11)],
		]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([]);
		$this->expectNoExtras();

		$data = $this->service->exportBoard(1);

		self::assertSame([100], array_column($data['stacks'][0]['cards'], 'id'));
		self::assertSame([110, 111], array_column($data['stacks'][1]['cards'], 'id'));
	}

	public function testExportIncludesArchivedCards(): void {
		$board = $this->board();
		$this->boardMapper->method('find')->with(1, true, true)->willReturn($board);
		$this->stackMapper->method('findAll')->with(1)->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->with([10])->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->with([10])->willReturn([10 => [$this->card(101, 10, true)]]);
		$this->expectNoExtras();

		$data = $this->service->exportBoard(1);

		$cards = $data['stacks'][0]['cards'];
		self::assertCount(2, $cards);
		self::assertSame(100, $cards[0]['id']);
		self::assertFalse($cards[0]['archived']);
		self::assertSame(101, $cards[1]['id']);
		self::assertTrue($cards[1]['archived']);
	}

	public function testExportIncludesArchivedCardsOfStacksWithoutActiveCards(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => []]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => [$this->card(101, 10, true)]]);
		$this->expectNoExtras();

		$data = $this->service->exportBoard(1);

		self::assertSame([101], array_column($data['stacks'][0]['cards'], 'id'));
	}

	public function testExportCanLeaveOutArchivedCards(): void {
		$board = $this->board();
		$this->boardMapper->method('find')->willReturn($board);
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->expects($this->never())->method('findAllArchivedForStacks');
		$this->expectNoExtras();

		$data = $this->service->exportBoard(1, new BoardExportOptions(includeArchivedCards: false));

		self::assertCount(1, $data['stacks'][0]['cards']);
	}

	public function testExportKeepsStateAndDateFields(): void {
		$board = $this->board();
		$card = $this->card(100, 10);
		$card->setCreatedAt(1689667796);
		$card->setLastModified(1689667800);
		$card->setDuedate(new \DateTime('2050-07-24T22:00:00+00:00'));
		$card->setStartdate(new \DateTime('2023-07-10T08:00:00+00:00'));
		$card->setDone(new \DateTime('2023-07-18T10:00:00+00:00'));

		$this->boardMapper->method('find')->willReturn($board);
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1, true)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$card]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->expectNoExtras();

		$data = $this->service->exportBoard(1);

		$exported = $data['stacks'][0]['cards'][0];
		self::assertTrue($data['stacks'][0]['isDoneColumn']);
		self::assertSame(100, $exported['id']);
		self::assertSame(10, $exported['stackId']);
		self::assertSame('2050-07-24T22:00:00+00:00', $exported['duedate']);
		self::assertSame('2023-07-10T08:00:00+00:00', $exported['startdate']);
		self::assertSame('2023-07-18T10:00:00+00:00', $exported['done']);
		self::assertSame(1689667796, $exported['createdAt']);
		self::assertSame(1689667800, $exported['lastModified']);
	}

	public function testExportIncludesLabelsAssignmentsAndComments(): void {
		$board = $this->board();

		$label = new Label();
		$label->setId(7);
		$label->setTitle('L1');
		$label->setCardId(100);

		$assignment = new Assignment();
		$assignment->setId(3);
		$assignment->setCardId(100);
		$assignment->setParticipant('alice');
		$assignment->setType(Assignment::TYPE_USER);

		$this->boardMapper->method('find')->willReturn($board);
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->with([100])->willReturn([$label]);
		$this->assignmentMapper->method('findIn')->with([100])->willReturn([$assignment]);
		$this->attachmentMapper->method('findAllForCards')->willReturn([]);
		$this->shareFileAttachmentExportService->method('exportAttachmentsForCards')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([$this->comment('55')]);

		$exported = $this->service->exportBoard(1)['stacks'][0]['cards'][0];

		self::assertCount(1, $exported['labels']);
		self::assertCount(1, $exported['assignedUsers']);
		self::assertCount(1, $exported['comments']);
		self::assertSame('55', $exported['comments'][0]['id']);
		self::assertSame('Hello', $exported['comments'][0]['message']);
		self::assertSame(1, $exported['commentsCount']);
	}

	public function testExportOnlyAssignsLabelsAndUsersToTheirOwnCard(): void {
		$label = new Label();
		$label->setId(7);
		$label->setTitle('L1');
		$label->setCardId(101);

		$assignment = new Assignment();
		$assignment->setId(3);
		$assignment->setCardId(101);
		$assignment->setParticipant('alice');
		$assignment->setType(Assignment::TYPE_USER);

		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10), $this->card(101, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->with([100, 101])->willReturn([$label]);
		$this->assignmentMapper->method('findIn')->with([100, 101])->willReturn([$assignment]);
		$this->attachmentMapper->method('findAllForCards')->willReturn([]);
		$this->shareFileAttachmentExportService->method('exportAttachmentsForCards')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);

		$cards = $this->service->exportBoard(1)['stacks'][0]['cards'];

		self::assertSame([], $cards[0]['labels']);
		self::assertSame([], $cards[0]['assignedUsers']);
		self::assertCount(1, $cards[1]['labels']);
		self::assertCount(1, $cards[1]['assignedUsers']);
	}

	public function testExportSortsCommentsByIdNotByFetchOrder(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->attachmentMapper->method('findAllForCards')->willReturn([]);
		$this->shareFileAttachmentExportService->method('exportAttachmentsForCards')->willReturn([]);
		$this->commentsManager->expects($this->once())
			->method('getForObject')
			->with('deckCard', '100')
			->willReturn([$this->comment('12'), $this->comment('3'), $this->comment('100')]);

		$exported = $this->service->exportBoard(1)['stacks'][0]['cards'][0];

		self::assertSame(['3', '12', '100'], array_column($exported['comments'], 'id'));
	}

	public function testExportCanLeaveOutCommentsAndAttachments(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->commentsManager->expects($this->never())->method('getForObject');
		// no file is read, but the cheap count query still runs
		$this->shareFileAttachmentExportService->expects($this->never())->method('exportAttachmentsForCards');
		$this->fileService->expects($this->never())->method('getFolder');
		$this->attachmentMapper->method('findAllForCards')->willReturn([]);
		$this->shareFileAttachmentExportService->method('countAttachmentsForCards')->willReturn([]);

		$exported = $this->service->exportBoard(1, new BoardExportOptions(
			includeComments: false,
			includeAttachments: false,
		))['stacks'][0]['cards'][0];

		self::assertSame([], $exported['comments']);
		self::assertSame([], $exported['attachments']);
		self::assertSame(0, $exported['commentsCount']);
	}

	public function testAttachmentCountIsReportedEvenWhenContentsAreExcluded(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);
		// one attachment in the app data folder, two shared from the Files app
		$this->attachmentMapper->method('findAllForCards')->willReturn([100 => [$this->attachment(1, 100)]]);
		$this->shareFileAttachmentExportService->method('countAttachmentsForCards')->willReturn([100 => 2]);
		$this->fileService->expects($this->never())->method('getFolder');

		$exported = $this->service->exportBoard(1, new BoardExportOptions(
			includeAttachments: false,
		))['stacks'][0]['cards'][0];

		self::assertSame([], $exported['attachments']);
		self::assertSame(3, $exported['attachmentCount']);
	}

	public function testAttachmentCountIgnoresFilesAppSharesOfOtherCards(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10), $this->card(101, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);
		$this->attachmentMapper->method('findAllForCards')->willReturn([]);
		$this->shareFileAttachmentExportService->method('countAttachmentsForCards')->willReturn([101 => 4]);

		$cards = $this->service->exportBoard(1, new BoardExportOptions(
			includeAttachments: false,
		))['stacks'][0]['cards'];

		self::assertSame(0, $cards[0]['attachmentCount']);
		self::assertSame(4, $cards[1]['attachmentCount']);
	}

	public function testExportMergesAppDataAndFilesAppAttachments(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getContent')->willReturn('hello');
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->with('notes.txt')->willReturn($file);
		$this->fileService->method('getFolder')->willReturn($folder);
		$this->permissionService->method('getUserId')->willReturn('alice');

		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);
		$this->attachmentMapper->expects($this->once())
			->method('findAllForCards')
			->with([100])
			->willReturn([100 => [$this->attachment(1, 100)]]);
		$this->shareFileAttachmentExportService->expects($this->once())
			->method('exportAttachmentsForCards')
			->with([100], 'alice')
			->willReturn([100 => [['type' => 'file', 'data' => 'shared.pdf', 'contentBase64' => 'x']]]);

		$exported = $this->service->exportBoard(1)['stacks'][0]['cards'][0];

		self::assertCount(2, $exported['attachments']);
		self::assertSame('notes.txt', $exported['attachments'][0]['data']);
		self::assertSame(base64_encode('hello'), $exported['attachments'][0]['contentBase64']);
		self::assertSame('shared.pdf', $exported['attachments'][1]['data']);
		self::assertSame(2, $exported['attachmentCount']);
	}

	public function testExportSerializesTheAttachmentMetadataAnImportNeeds(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getContent')->willReturn('hello');
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->willReturn($file);
		$this->fileService->method('getFolder')->willReturn($folder);

		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);
		$this->attachmentMapper->method('findAllForCards')->willReturn([100 => [$this->attachment(1, 100)]]);
		$this->shareFileAttachmentExportService->method('exportAttachmentsForCards')->willReturn([]);

		$exported = $this->service->exportBoard(1)['stacks'][0]['cards'][0]['attachments'][0];

		// the importer keys off `type` === 'file' and needs the content plus its metadata
		self::assertSame([
			'type' => 'file',
			'data' => 'notes.txt',
			'createdBy' => 'admin',
			'createdAt' => 1689667796,
			'lastModified' => 1689667800,
			'contentBase64' => base64_encode('hello'),
		], $exported);
	}

	public function testExportSkipsAttachmentsThatCannotBeRead(): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->willThrowException(new NotFoundException('gone'));
		$this->fileService->method('getFolder')->willReturn($folder);
		$this->logger->expects($this->once())->method('info');

		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);
		$this->attachmentMapper->method('findAllForCards')->willReturn([100 => [$this->attachment(1, 100)]]);
		$this->shareFileAttachmentExportService->method('exportAttachmentsForCards')->willReturn([]);

		$exported = $this->service->exportBoard(1)['stacks'][0]['cards'][0];

		// a broken attachment must not abort the export of the whole board
		self::assertSame([], $exported['attachments']);
		self::assertSame(0, $exported['attachmentCount']);
	}

	public function testExportIgnoresAttachmentsThatAreNotStoredInTheAppData(): void {
		$this->fileService->expects($this->never())->method('getFolder');

		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->method('findAllArchivedForStacks')->willReturn([10 => []]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);
		// `file` rows point at a Files app node and are exported by the share service instead
		$this->attachmentMapper->method('findAllForCards')->willReturn([100 => [$this->attachment(1, 100, 'file', '42')]]);
		$this->shareFileAttachmentExportService->method('exportAttachmentsForCards')->willReturn([]);

		$exported = $this->service->exportBoard(1)['stacks'][0]['cards'][0];

		self::assertSame([], $exported['attachments']);
	}

	public function testExportBoardsSkipsTrashedBoards(): void {
		$deleted = $this->board(2);
		$deleted->setDeletedAt(time());

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(1, true, true)
			->willReturn($this->board(1));
		$this->stackMapper->method('findAll')->willReturn([]);
		$this->expectNoExtras();

		$exported = $this->service->exportBoards([$this->board(1), $deleted]);

		self::assertSame([1], array_keys($exported));
	}

	public function testExportBoardsKeepsOneEntryPerBoard(): void {
		$this->boardMapper->method('find')->willReturnCallback(fn (int $id) => $this->board($id));
		$this->stackMapper->method('findAll')->willReturn([]);
		$this->expectNoExtras();

		$exported = $this->service->exportBoards([$this->board(1), $this->board(2), $this->board(3)]);

		self::assertSame([1, 2, 3], array_keys($exported));
		self::assertSame('Board 2', $exported[2]['title']);
	}

	public function testExportBoardsPassesTheOptionsOn(): void {
		$this->boardMapper->method('find')->willReturn($this->board());
		$this->stackMapper->method('findAll')->willReturn([$this->stack(10, 1)]);
		$this->cardMapper->method('findAllForStacks')->willReturn([10 => [$this->card(100, 10)]]);
		$this->cardMapper->expects($this->never())->method('findAllArchivedForStacks');
		$this->shareFileAttachmentExportService->expects($this->never())->method('exportAttachmentsForCards');
		$this->attachmentMapper->method('findAllForCards')->willReturn([]);
		$this->shareFileAttachmentExportService->method('countAttachmentsForCards')->willReturn([]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignmentMapper->method('findIn')->willReturn([]);
		$this->commentsManager->method('getForObject')->willReturn([]);

		$this->service->exportBoards([$this->board(1)], new BoardExportOptions(
			includeArchivedCards: false,
			includeAttachments: false,
		));
	}

	public function testExportBoardsReturnsNothingForAnEmptyList(): void {
		$this->boardMapper->expects($this->never())->method('find');

		self::assertSame([], $this->service->exportBoards([]));
	}
}
