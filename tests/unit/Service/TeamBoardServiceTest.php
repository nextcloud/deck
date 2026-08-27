<?php

declare(strict_types = 1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TeamBoardServiceTest extends TestCase {
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var CirclesService|MockObject */
	private $circlesService;
	private TeamBoardService $service;
	private $userId1 = 'user1';
	private $userId2 = 'user2';

	public function setUp(): void {
		parent::setUp();
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->circlesService = $this->createMock(CirclesService::class);
		$this->service = new TeamBoardService(
			$this->boardMapper,
			$this->circlesService,
		);
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
	}
}
