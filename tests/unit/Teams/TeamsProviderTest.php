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
use OCP\IURLGenerator;
use OCP\Teams\ITeamAppProvider;
use OCP\Teams\ITeamManager;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamAppNavigationAction;
use OCP\Teams\TeamResource;
use PHPUnit\Framework\TestCase;

final class TeamsProviderTest extends TestCase {
	private function teamManagerWithSharedBoardIds(array $ids): ITeamManager {
		$resourceProvider = $this->createMock(ITeamResourceProvider::class);
		$resourceProvider->method('getId')->willReturn('deck');
		$teamManager = $this->createMock(ITeamManager::class);
		$teamManager->method('getSharedWith')->with('team', 'member')->willReturn(array_map(static fn (int $id): TeamResource => new TeamResource($resourceProvider, (string)$id, 'Board', '/deck/' . $id), $ids));
		return $teamManager;
	}

	public function testPhpDiscoversOnlyActiveTeamBoards(): void {
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
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $path): string => 'https://cloud.example' . $path);
		$provider = new TeamsProvider($service, $boardMapper, $this->teamManagerWithSharedBoardIds([1, 2, 3]), $this->createMock(IL10N::class), $urls);
		$result = $provider->getNavigation('team', 'member', false, null);
		$this->assertSame(['1'], array_column($result->entries, 'id'));
		$this->assertSame('M16,5V18H21V5M4,18H9V5H4M10,18H15V5H10V18Z', $result->entries[0]->icon);
		$this->assertSame('https://cloud.example/apps/deck/#/board/1', $result->entries[0]->referenceUrl);
		$this->assertSame($result->entries[0]->referenceUrl, $result->entries[0]->href);
		$this->assertNull($result->createAction);
	}

	public function testAdminCreationActionDeclaresASinglePrompt(): void {
		$boardMapper = $this->createMock(BoardMapper::class);
		$boardMapper->method('findAllAttachedToTeam')->with('team')->willReturn([]);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$provider = new TeamsProvider($this->createMock(BoardService::class), $boardMapper, $this->teamManagerWithSharedBoardIds([]), $l10n, $this->createMock(IURLGenerator::class));
		$result = $provider->getNavigation('team', 'member', true, null);
		$this->assertInstanceOf(TeamAppNavigationAction::class, $result->createAction);
		$this->assertSame('deck-create', $result->createAction->id);
		$this->assertSame('Board name', $result->createAction->prompt);
	}

	public function testCreationUsesTheAuthorizedTeamAndUser(): void {
		$service = $this->createMock(BoardService::class);
		$board = new Board();
		$board->setId(33);
		$service->expects($this->once())->method('createForTeam')->with('New', 'member', null, 'team')->willReturn($board);
		$provider = new TeamsProvider($service, $this->createMock(BoardMapper::class), $this->createMock(ITeamManager::class), $this->createMock(IL10N::class), $this->createMock(IURLGenerator::class));
		$this->assertSame('33', $provider->addResource('team', 'Team', 'member', true, null, ' New '));
	}

	public function testNonadminCannotCreateBoards(): void {
		$service = $this->createMock(BoardService::class);
		$service->expects($this->never())->method('createForTeam');
		$this->expectException(\InvalidArgumentException::class);
		(new TeamsProvider($service, $this->createMock(BoardMapper::class), $this->createMock(ITeamManager::class), $this->createMock(IL10N::class), $this->createMock(IURLGenerator::class)))->addResource('team', 'Team', 'member', false, null, 'New');
	}

	public function testCreationRejectsEmptyNames(): void {
		$service = $this->createMock(BoardService::class);
		$service->expects($this->never())->method('createForTeam');
		$this->expectException(\InvalidArgumentException::class);
		(new TeamsProvider($service, $this->createMock(BoardMapper::class), $this->createMock(ITeamManager::class), $this->createMock(IL10N::class), $this->createMock(IURLGenerator::class)))->addResource('team', 'Team', 'member', true, null, ' ');
	}

	public function testImplementsCoreProviderWithoutLoadingATeamsModule(): void {
		if (!interface_exists(ITeamAppProvider::class)) {
			$this->markTestSkipped('Requires the core Teams app provider API');
		}
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->expects($this->never())->method('linkTo');
		$provider = new TeamsProvider($this->createMock(BoardService::class), $this->createMock(BoardMapper::class), $this->createMock(ITeamManager::class), $this->createMock(IL10N::class), $urlGenerator);
		$this->assertInstanceOf(ITeamAppProvider::class, $provider);
		$this->assertSame('deck', $provider->getId());
		$this->assertSame(20, $provider->getOrder());
	}
}
