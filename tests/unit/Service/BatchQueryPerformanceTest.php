<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Nextcloud GmbH and Nextcloud contributors
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Cache\AttachmentCacheHelper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\Validators\AttachmentServiceValidator;
use OCA\Deck\Validators\CardServiceValidator;
use OCP\AppFramework\IAppContainer;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Verifies that the batch-query optimisations hold for the reference scenario
 * described in the feature specification:
 *
 *   13 boards × 100 cards × 5 attachments = 1 300 cards / 6 500 attachments
 *
 * Each test asserts that a critical DB-layer method is called exactly once
 * (batched), never once-per-card or once-per-board.
 */
class BatchQueryPerformanceTest extends TestCase {
	private const BOARDS = 13;
	private const CARDS_PER_BOARD = 100;
	private const ATTACHMENTS_PER_CARD = 5;
	/** 13 × 100 = 1 300 */
	private const TOTAL_CARDS = self::BOARDS * self::CARDS_PER_BOARD;
	private AttachmentMapper|MockObject $attachmentMapper;
	private AttachmentCacheHelper|MockObject $attachmentCacheHelper;
	private FileService|MockObject $fileServiceImpl;
	private FilesAppService|MockObject $filesAppServiceImpl;
	private AttachmentService $attachmentService;
	private CardMapper|MockObject $cardMapper;
	private StackMapper|MockObject $stackMapper;
	private LabelMapper|MockObject $labelMapper;
	private AssignmentMapper|MockObject $assignedUsersMapper;
	private AttachmentService|MockObject $attachmentServiceMock;
	private BoardService|MockObject $boardService;
	private IUserManager|MockObject $userManager;
	private ICommentsManager|MockObject $commentsManager;
	private IReferenceManager|MockObject $referenceManager;
	private CardService $cardService;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpAttachmentService();
		$this->setUpCardService();
	}

	/**
	 * Cold-cache scenario: 13 boards × 100 cards = 1 300 card IDs.
	 *
	 */
	public function testCountForCardsIssuesSingleBatchQueryFor1300Cards(): void {
		$cardIds = range(1, self::TOTAL_CARDS);

		// Cold cache: every card is uncached
		$this->attachmentCacheHelper
			->expects($this->exactly(self::TOTAL_CARDS))
			->method('getAttachmentCount')
			->willReturn(null);

		// deck_attachment table: queried ONCE for all 1 300 IDs
		$this->attachmentMapper
			->expects($this->once())
			->method('findCountByCardIds')
			->with($cardIds)
			->willReturn(array_fill_keys($cardIds, self::ATTACHMENTS_PER_CARD));

		// FilesApp shares table: also queried ONCE for all 1 300 IDs
		$this->filesAppServiceImpl
			->expects($this->once())
			->method('getAttachmentCountForCards')
			->with($cardIds)
			->willReturn(array_fill_keys($cardIds, 0));

		// Results must be written to cache (one entry per uncached card)
		$this->attachmentCacheHelper
			->expects($this->exactly(self::TOTAL_CARDS))
			->method('setAttachmentCount');

		$counts = $this->attachmentService->countForCards($cardIds);

		$this->assertCount(self::TOTAL_CARDS, $counts);
		foreach ($counts as $count) {
			$this->assertSame(self::ATTACHMENTS_PER_CARD, $count);
		}
	}

	/**
	 * Warm-cache scenario: all 1 300 cards are already cached.
	 *
	 * No DB query of any kind must be issued.
	 */
	public function testCountForCardsSkipsCachedCardsAndIssuesNoDatabaseQuery(): void {
		$cardIds = range(1, self::TOTAL_CARDS);

		$this->attachmentCacheHelper
			->expects($this->exactly(self::TOTAL_CARDS))
			->method('getAttachmentCount')
			->willReturn(self::ATTACHMENTS_PER_CARD);

		$this->attachmentMapper->expects($this->never())->method('findCountByCardIds');
		$this->filesAppServiceImpl->expects($this->never())->method('getAttachmentCountForCards');
		$this->attachmentCacheHelper->expects($this->never())->method('setAttachmentCount');

		$counts = $this->attachmentService->countForCards($cardIds);

		$this->assertCount(self::TOTAL_CARDS, $counts);
		foreach ($counts as $count) {
			$this->assertSame(self::ATTACHMENTS_PER_CARD, $count);
		}
	}

	/**
	 * Empty input must short-circuit without any DB or cache interaction.
	 */
	public function testCountForCardsWithEmptyInputReturnsEmptyWithoutAnyQuery(): void {
		$this->attachmentMapper->expects($this->never())->method('findCountByCardIds');
		$this->filesAppServiceImpl->expects($this->never())->method('getAttachmentCountForCards');
		$this->attachmentCacheHelper->expects($this->never())->method('getAttachmentCount');
		$this->attachmentCacheHelper->expects($this->never())->method('setAttachmentCount');

		$this->assertSame([], $this->attachmentService->countForCards([]));
	}

	/**
	 * enrichCards() with 100 cards (one board's worth) must call
	 * AttachmentService::countForCards() exactly once, passing all 100 IDs
	 * in a single call — never once-per-card.
	 *
	 */
	public function testEnrichCardsCallsAttachmentCountAsSingleBatchQuery(): void {
		$cards = $this->buildCards(self::CARDS_PER_BOARD, stackId: 1);
		$cardIds = array_map(fn (Card $c) => $c->getId(), $cards);

		$this->setUpEnrichCardsStubs(stackId: 1);

		// THE ASSERTION: attachment counts must be fetched in one batch call
		$this->attachmentServiceMock
			->expects($this->once())
			->method('countForCards')
			->with($cardIds)
			->willReturn(array_fill_keys($cardIds, self::ATTACHMENTS_PER_CARD));

		$result = $this->cardService->enrichCards($cards);

		$this->assertCount(self::CARDS_PER_BOARD, $result);
		foreach ($result as $cardDetail) {
			$this->assertSame(self::ATTACHMENTS_PER_CARD, $cardDetail->getAttachmentCount());
		}
	}

	/**
	 * enrichCards() across all 13 boards (1 300 cards, 13 unique stacks) must
	 * call StackMapper::findByIds() exactly once, passing all 13 unique stack
	 * IDs — never once-per-card (which would produce 1 300 individual
	 * StackMapper::find() queries).
	 *
	 */
	public function testEnrichCardsCallsStackFetchAsSingleBatchQueryAcross13Boards(): void {
		$cards = [];
		$expectedStackIds = [];
		$stackMap = [];

		for ($board = 1; $board <= self::BOARDS; $board++) {
			$stackId = $board * 10;
			$expectedStackIds[] = $stackId;

			$stack = new Stack();
			$stack->setId($stackId);
			$stack->setBoardId($board);
			$stackMap[$stackId] = $stack;

			foreach ($this->buildCards(self::CARDS_PER_BOARD, stackId: $stackId, idOffset: ($board - 1) * self::CARDS_PER_BOARD) as $card) {
				$cards[] = $card;
			}
		}
		sort($expectedStackIds);

		// Loose stubs for non-critical collaborators
		$this->attachmentServiceMock->method('countForCards')->willReturn([]);
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignedUsersMapper->method('findIn')->willReturn([]);
		$this->userManager->method('get')->willReturn($this->createMock(IUser::class));
		$this->commentsManager->method('getNumberOfCommentsForObject')->willReturn(0);
		$this->commentsManager->method('getReadMark')->willReturn(null);
		$this->boardService->method('find')->willReturn($this->createMock(Board::class));
		$this->referenceManager->method('extractReferences')->willReturn([]);

		// THE ASSERTION: all 13 stack IDs fetched in a single findByIds() call
		$this->stackMapper
			->expects($this->once())
			->method('findByIds')
			->with($this->callback(function (array $ids) use ($expectedStackIds): bool {
				sort($ids);
				return $ids === $expectedStackIds;
			}))
			->willReturn($stackMap);

		$this->cardService->enrichCards($cards);
	}

	/**
	 * enrichCards() with 100 cards must call LabelMapper::findAssignedLabelsForCards()
	 * and AssignmentMapper::findIn() each exactly once with all 100 card IDs.
	 *
	 */
	public function testEnrichCardsCallsLabelAndUserQueriesOnceForAllCards(): void {
		$cards = $this->buildCards(self::CARDS_PER_BOARD, stackId: 1);
		$cardIds = array_map(fn (Card $c) => $c->getId(), $cards);

		$this->setUpEnrichCardsStubs(stackId: 1);
		$this->attachmentServiceMock->method('countForCards')->willReturn([]);

		// THE ASSERTIONS: label and user data must be fetched in one call each
		$this->labelMapper
			->expects($this->once())
			->method('findAssignedLabelsForCards')
			->with($cardIds)
			->willReturn([]);

		$this->assignedUsersMapper
			->expects($this->once())
			->method('findIn')
			->with($cardIds)
			->willReturn([]);

		$result = $this->cardService->enrichCards($cards);

		$this->assertCount(self::CARDS_PER_BOARD, $result);
	}

	/**
	 * Build an array of Card entities with sequential IDs.
	 *
	 * @param int $idOffset Added to each sequential ID so test sets are unique.
	 * @return Card[]
	 */
	private function buildCards(int $count, int $stackId, int $idOffset = 0): array {
		$cards = [];
		for ($i = 1; $i <= $count; $i++) {
			$card = new Card();
			$card->setId($idOffset + $i);
			$card->setStackId($stackId);
			$card->setTitle('Card ' . ($idOffset + $i));
			$cards[] = $card;
		}
		return $cards;
	}

	/**
	 * Configure loose stubs for all enrichCards() collaborators that are NOT
	 * under test in a given test method, so they don't interfere.
	 */
	private function setUpEnrichCardsStubs(int $stackId): void {
		$stack = new Stack();
		$stack->setId($stackId);
		$stack->setBoardId(1);

		$this->userManager->method('get')->willReturn($this->createMock(IUser::class));
		$this->commentsManager->method('getNumberOfCommentsForObject')->willReturn(0);
		$this->commentsManager->method('getReadMark')->willReturn(null);
		$this->stackMapper->method('findByIds')->willReturn([$stackId => $stack]);
		$this->boardService->method('find')->willReturn($this->createMock(Board::class));
		$this->referenceManager->method('extractReferences')->willReturn([]);
		// findAssignedLabelsForCards and findIn are stubbed here only when not the
		// method under test. countForCards is intentionally omitted so that tests
		// that assert on it can register their own expectation without a catch-all
		// stub taking priority.
		$this->labelMapper->method('findAssignedLabelsForCards')->willReturn([]);
		$this->assignedUsersMapper->method('findIn')->willReturn([]);
	}

	private function setUpAttachmentService(): void {
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->attachmentCacheHelper = $this->createMock(AttachmentCacheHelper::class);
		$this->fileServiceImpl = $this->createMock(FileService::class);
		// FilesAppService implements ICustomAttachmentService, so mock the full class
		$this->filesAppServiceImpl = $this->createMock(FilesAppService::class);

		$appContainer = $this->createMock(IAppContainer::class);
		$appContainer->method('get')
			->willReturnMap([
				[FileService::class, $this->fileServiceImpl],
				[FilesAppService::class, $this->filesAppServiceImpl],
			]);

		$application = $this->createMock(Application::class);
		$application->method('getContainer')->willReturn($appContainer);

		$this->attachmentService = new AttachmentService(
			$this->attachmentMapper,
			$this->createMock(CardMapper::class),
			$this->createMock(IUserManager::class),
			$this->createMock(ChangeHelper::class),
			$this->createMock(PermissionService::class),
			$application,
			$this->attachmentCacheHelper,
			'user1',
			$this->createMock(IL10N::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(AttachmentServiceValidator::class),
		);
	}

	private function setUpCardService(): void {
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->assignedUsersMapper = $this->createMock(AssignmentMapper::class);
		$this->attachmentServiceMock = $this->createMock(AttachmentService::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->referenceManager = $this->createMock(IReferenceManager::class);

		$this->cardService = new CardService(
			$this->cardMapper,
			$this->stackMapper,
			$this->createMock(BoardMapper::class),
			$this->labelMapper,
			$this->createMock(LabelService::class),
			$this->createMock(PermissionService::class),
			$this->boardService,
			$this->createMock(NotificationHelper::class),
			$this->assignedUsersMapper,
			$this->attachmentServiceMock,
			$this->createMock(ActivityManager::class),
			$this->commentsManager,
			$this->userManager,
			$this->createMock(ChangeHelper::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IRequest::class),
			$this->createMock(CardServiceValidator::class),
			$this->createMock(AssignmentService::class),
			$this->referenceManager,
			'user1',
		);
	}
}
