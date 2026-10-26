<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Tests\Teams;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Teams\TeamsProvider;
use OCP\IL10N;
use OCP\Teams\ITeamAppProvider;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamAppActionResult;
use OCP\Teams\TeamAppResourceKey;
use OCP\Teams\TeamResource;
use PHPUnit\Framework\TestCase;

final class TeamsProviderTest extends TestCase {
	public function testPhpDiscoversOnlyActiveTeamBoardsAndTheirResourceKeys(): void {
		$service = $this->createMock(BoardService::class);
		$boardMapper = $this->createMock(BoardMapper::class);
		$boards = [];
		foreach ([[1, false, 0], [2, true, 0], [3, false, 123]] as [$id, $archived, $deleted]) {
			$board = new Board();
			$board->setId($id);
			$board->setTitle('Board ' . $id);
			$board->setTeamId('team');
			$board->setArchived($archived);
			$board->setDeletedAt($deleted);
			$boards[] = $board;
		}
		$boardMapper->method('findAllAttachedToTeam')->with('team')->willReturn($boards);
		$resources = $this->createMock(ITeamResourceProvider::class);
		$resources->method('getId')->willReturn('deck');
		$provider = new TeamsProvider($service, $boardMapper, $this->createMock(IL10N::class));
		$result = $provider->getNavigation(['teamId' => 'team', 'isAdmin' => false, 'resources' => array_map(static fn (int $id): TeamResource => new TeamResource($resources, (string)$id, 'Board', '/deck/' . $id), [1, 2, 3])]);
		$this->assertSame(['1'], array_column($result->entries, 'id'));
		$this->assertSame('/deck/1', $result->entries[0]->href);
		$this->assertEquals([new TeamAppResourceKey('deck', '1')], $result->resourceKeys);
		$this->assertSame([], $result->actions);
	}

	public function testCreationUsesTheAuthorizedTeamAndUser(): void {
		$service = $this->createMock(BoardService::class);
		$service->expects($this->once())->method('createForTeam')->with('New', 'member', null, 'team')->willReturn(new Board());
		$provider = new TeamsProvider($service, $this->createMock(BoardMapper::class), $this->createMock(IL10N::class));
		$this->assertEquals(new TeamAppActionResult(), $provider->executeAction(['isAdmin' => true, 'userId' => 'member', 'teamId' => 'team'], 'deck-create', ['value' => ' New ']));
	}

	public function testNonadminCannotCreateBoards(): void {
		$service = $this->createMock(BoardService::class);
		$service->expects($this->never())->method('createForTeam');
		$this->expectException(\InvalidArgumentException::class);
		(new TeamsProvider($service, $this->createMock(BoardMapper::class), $this->createMock(IL10N::class)))->executeAction(['isAdmin' => false], 'deck-create', ['value' => 'New']);
	}

	public function testCreationRejectsEmptyNames(): void {
		$service = $this->createMock(BoardService::class);
		$service->expects($this->never())->method('createForTeam');
		$this->expectException(\InvalidArgumentException::class);
		(new TeamsProvider($service, $this->createMock(BoardMapper::class), $this->createMock(IL10N::class)))->executeAction(['isAdmin' => true], 'deck-create', ['value' => ' ']);
	}

	public function testImplementsCoreProviderAndLoadsItsModule(): void {
		if (!interface_exists(ITeamAppProvider::class)) {
			$this->markTestSkipped('Requires the core Teams app provider API');
		}
		$provider = new TeamsProvider($this->createMock(BoardService::class), $this->createMock(BoardMapper::class), $this->createMock(IL10N::class));
		$this->assertInstanceOf(ITeamAppProvider::class, $provider);
		$this->assertSame('deck', $provider->getId());
		$this->assertNull($provider->load());
		$this->assertSame(20, $provider->getOrder());
	}
}
