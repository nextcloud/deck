<?php

declare(strict_types = 1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TeamBoardServiceTest extends TestCase {
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var CirclesService|MockObject */
	private $circlesService;
	private TeamBoardService $service;
	private IEventDispatcher&MockObject $eventDispatcher;
	/** @var list<CriticalActionPerformedEvent> */
	private array $auditEvents = [];
	private $userId1 = 'user1';
	private $userId2 = 'user2';

	public function setUp(): void {
		parent::setUp();
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->circlesService = $this->createMock(CirclesService::class);
		$this->auditEvents = [];
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (Event $event): void {
				if ($event instanceof CriticalActionPerformedEvent) {
					$this->auditEvents[] = $event;
				}
			});
		$this->service = new TeamBoardService(
			$this->boardMapper,
			$this->circlesService,
			$this->eventDispatcher,
		);
	}

	/**
	 * @return list<?string>
	 */
	private function getAuditOperations(): array {
		return array_map(static fn (CriticalActionPerformedEvent $event): ?string => $event->getOperation(), $this->auditEvents);
	}

	public function testHandleMemberLeftTeamTransfersOwnership(): void {
		$board = new Board();
		$board->setId(10);
		$board->setOwner($this->userId1);
		$board->setTeamId('team-a');

		$this->boardMapper->expects($this->once())
			->method('findAllAttachedToTeam')
			->with('team-a')
			->willReturn([$board]);
		$this->circlesService->expects($this->once())
			->method('findNextMemberUserId')
			->with('team-a', $this->userId1)
			->willReturn($this->userId2);
		$this->boardMapper->expects($this->once())
			->method('transferOwnership')
			->with($this->userId1, $this->userId2, 10);
		$this->boardMapper->expects($this->never())
			->method('delete');

		$this->service->handleMemberLeftTeam('team-a', $this->userId1);

		$this->assertSame(['deck.board.ownership_transferred'], $this->getAuditOperations());
		$this->assertSame(['boardId' => 10, 'previousOwner' => $this->userId1, 'newOwner' => $this->userId2, 'teamId' => 'team-a'], $this->auditEvents[0]->getParameters());
	}

	public function testHandleMemberLeftTeamDeletesWhenNoNextMember(): void {
		$board = new Board();
		$board->setId(11);
		$board->setOwner($this->userId1);
		$board->setTeamId('team-a');

		$this->boardMapper->expects($this->once())
			->method('findAllAttachedToTeam')
			->with('team-a')
			->willReturn([$board]);
		$this->circlesService->expects($this->once())
			->method('findNextMemberUserId')
			->with('team-a', $this->userId1)
			->willReturn(null);
		$this->boardMapper->expects($this->once())
			->method('delete')
			->with($board);
		$this->boardMapper->expects($this->never())
			->method('transferOwnership');

		$this->service->handleMemberLeftTeam('team-a', $this->userId1);

		$this->assertSame(['deck.board.purged'], $this->getAuditOperations());
		$this->assertSame(['boardId' => 11, 'owner' => $this->userId1, 'teamId' => 'team-a'], $this->auditEvents[0]->getParameters());
	}

	public function testHandleMemberLeftTeamSkipsNonOwnerBoards(): void {
		$board = new Board();
		$board->setId(12);
		$board->setOwner($this->userId2);
		$board->setTeamId('team-a');

		$this->boardMapper->expects($this->once())
			->method('findAllAttachedToTeam')
			->with('team-a')
			->willReturn([$board]);
		$this->circlesService->expects($this->never())
			->method('findNextMemberUserId');
		$this->boardMapper->expects($this->never())
			->method('transferOwnership');
		$this->boardMapper->expects($this->never())
			->method('delete');

		$this->service->handleMemberLeftTeam('team-a', $this->userId1);
	}

	public function testDeleteBoardsAttachedToTeam(): void {
		$boardA = new Board();
		$boardA->setId(20);
		$boardB = new Board();
		$boardB->setId(21);

		$this->boardMapper->expects($this->once())
			->method('findAllAttachedToTeam')
			->with('team-a')
			->willReturn([$boardA, $boardB]);
		$this->boardMapper->expects($this->exactly(2))
			->method('delete')
			->withConsecutive([$boardA], [$boardB]);

		$this->service->deleteBoardsAttachedToTeam('team-a');

		$this->assertSame(['deck.board.purged', 'deck.board.purged'], $this->getAuditOperations());
		$this->assertSame(['boardId' => 21, 'teamId' => 'team-a'], $this->auditEvents[1]->getParameters());
	}
}
