<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Tests\Teams;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Teams\DeckTeamResourceProvider;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IURLGenerator;
use OCP\Teams\ITeamManager;
use OCP\Teams\ITeamOwnedResourceProvider;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamResource;
use PHPUnit\Framework\TestCase;

final class DeckTeamResourceProviderTest extends TestCase {
	public function testApplicationRegistersOwnedProviderAsResourceProvider(): void {
		if (!interface_exists(ITeamOwnedResourceProvider::class)) {
			$this->markTestSkipped('Requires the core owned resource provider API');
		}
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects($this->once())->method('registerTeamResourceProvider')->with(DeckTeamResourceProvider::class);
		$app = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
		$app->register($context);
	}

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
		$urls->expects($this->once())->method('linkToRouteAbsolute')->with('deck.page.indexBoard', ['boardId' => '1'])->willReturn('https://cloud.example/index.php/apps/deck/board/1');
		$provider = new DeckTeamResourceProvider($service, $boardMapper, $this->teamManagerWithSharedBoardIds([1, 2, 3]), $urls);
		$result = $provider->getOwnedByTeam('team', 'member', false, null);
		$this->assertSame(['1'], array_map(static fn (TeamResource $resource) => $resource->getId(), $result));
		$this->assertSame('https://cloud.example/index.php/apps/deck/board/1', $result[0]->getUrl());
		$this->assertSame('https://cloud.example/apps/deck/#/board/1', $result[0]->getReferenceUrl());
		$this->assertFalse($provider->canCreateResource('team', 'member', false, null));
	}

	public function testNavigationPreservesTheBoardColorIcon(): void {
		$board = new Board();
		$board->setId(1);
		$board->setTitle('Board');
		$board->setTeamId('team');
		$boardMapper = $this->createMock(BoardMapper::class);
		$boardMapper->method('findAllAttachedToTeam')->with('team')->willReturn([$board]);

		$resourceProvider = $this->createMock(ITeamResourceProvider::class);
		$resourceProvider->method('getId')->willReturn('deck');
		$icon = '<svg xmlns="http://www.w3.org/2000/svg"><circle fill="#0082c9" /></svg>';
		$sharedBoard = new TeamResource($resourceProvider, '1', 'Board', '/deck/1', $icon, '/apps/deck/img/deck.svg');
		$teamManager = $this->createMock(ITeamManager::class);
		$teamManager->method('getSharedWith')->with('team', 'member')->willReturn([$sharedBoard]);

		$provider = new DeckTeamResourceProvider(
			$this->createMock(BoardService::class),
			$boardMapper,
			$teamManager,
			$this->createMock(IURLGenerator::class),
		);
		$result = $provider->getOwnedByTeam('team', 'member', false, null);

		$this->assertSame($icon, $result[0]->getIconSvg());
		$this->assertSame('/apps/deck/img/deck.svg', $result[0]->getIconURL());
	}

	public function testAdminCanCreateABoard(): void {
		$boardMapper = $this->createMock(BoardMapper::class);
		$boardMapper->method('findAllAttachedToTeam')->with('team')->willReturn([]);
		$provider = new DeckTeamResourceProvider($this->createMock(BoardService::class), $boardMapper, $this->teamManagerWithSharedBoardIds([]), $this->createMock(IURLGenerator::class));
		$this->assertSame([], $provider->getOwnedByTeam('team', 'member', true, null));
		$this->assertTrue($provider->canCreateResource('team', 'member', true, null));
	}

	public function testCreationUsesTheAuthorizedTeamAndUser(): void {
		$service = $this->createMock(BoardService::class);
		$board = new Board();
		$board->setId(33);
		$service->expects($this->once())->method('createForTeam')->with('Team', 'member', null, 'team')->willReturn($board);
		$provider = new DeckTeamResourceProvider($service, $this->createMock(BoardMapper::class), $this->createMock(ITeamManager::class), $this->createMock(IURLGenerator::class));
		$this->assertSame('33', $provider->createResource('team', 'Team', 'member', true, null));
	}

	public function testNonadminCannotCreateBoards(): void {
		$service = $this->createMock(BoardService::class);
		$service->expects($this->never())->method('createForTeam');
		$this->expectException(\InvalidArgumentException::class);
		(new DeckTeamResourceProvider($service, $this->createMock(BoardMapper::class), $this->createMock(ITeamManager::class), $this->createMock(IURLGenerator::class)))->createResource('team', 'Team', 'member', false, null);
	}

	public function testCreationRejectsEmptyNames(): void {
		$service = $this->createMock(BoardService::class);
		$service->expects($this->never())->method('createForTeam');
		$this->expectException(\InvalidArgumentException::class);
		(new DeckTeamResourceProvider($service, $this->createMock(BoardMapper::class), $this->createMock(ITeamManager::class), $this->createMock(IURLGenerator::class)))->createResource('team', ' ', 'member', true, null);
	}

	public function testImplementsCoreProviderWithoutLoadingATeamsModule(): void {
		if (!interface_exists(ITeamOwnedResourceProvider::class)) {
			$this->markTestSkipped('Requires the core Teams app provider API');
		}
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->expects($this->never())->method('linkTo');
		$provider = new DeckTeamResourceProvider($this->createMock(BoardService::class), $this->createMock(BoardMapper::class), $this->createMock(ITeamManager::class), $urlGenerator);
		$this->assertInstanceOf(ITeamOwnedResourceProvider::class, $provider);
		$this->assertInstanceOf(ITeamResourceProvider::class, $provider);
	}

	public function testResourceProviderOperationsUseExistingBoardBehavior(): void {
		$board = new Board();
		$board->setId(33);
		$board->setTitle('Board');
		$board->setColor('0082c9');
		$mapper = $this->createMock(BoardMapper::class);
		$mapper->expects($this->once())->method('findAllByTeam')->with('team')->willReturn([$board]);
		$mapper->expects($this->once())->method('isSharedWithTeam')->with(33, 'team')->willReturn(true);
		$mapper->expects($this->once())->method('findTeamsForBoard')->with(33)->willReturn(['team']);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->with('deck.page.indexBoard', ['boardId' => 33])->willReturn('/deck/33');
		$urls->method('imagePath')->with('deck', 'deck-current.svg')->willReturn('/deck.svg');
		$urls->method('getAbsoluteURL')->with('/deck.svg')->willReturn('https://example.test/deck.svg');
		$provider = new DeckTeamResourceProvider($this->createMock(BoardService::class), $mapper, $this->createMock(ITeamManager::class), $urls);

		$this->assertSame('deck', $provider->getId());
		$this->assertSame('Deck', $provider->getName());
		$this->assertNotEmpty($provider->getIconSvg());
		$resources = $provider->getSharedWith('team');
		$this->assertCount(1, $resources);
		$this->assertSame($provider, $resources[0]->getProvider());
		$this->assertSame('33', $resources[0]->getId());
		$this->assertSame('/deck/33', $resources[0]->getUrl());
		$this->assertStringContainsString('#0082c9', $resources[0]->getIconSvg());
		$this->assertTrue($provider->isSharedWithTeam('team', '33'));
		$this->assertSame(['team'], $provider->getTeamsForResource('33'));
	}
}
