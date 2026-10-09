<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Teams;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IURLGenerator;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeckTeamResourceProviderTest extends TestCase {
	private CardMapper&MockObject $cardMapper;
	private ITeamResourceProvider&MockObject $resourceProvider;
	private DeckTeamResourceProvider $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->resourceProvider = $this->createMock(ITeamResourceProvider::class);
		$this->provider = new DeckTeamResourceProvider(
			$this->createMock(BoardMapper::class),
			$this->cardMapper,
			$this->createMock(IURLGenerator::class),
		);
	}

	private function resource(string $id): TeamResource {
		return new TeamResource($this->resourceProvider, $id, 'Board ' . $id, '/deck/' . $id);
	}

	private function expectCardIds(array $boardIds, array $cardIds): void {
		$query = $this->createMock(IQueryBuilder::class);
		$result = $this->createMock(IResult::class);
		$this->cardMapper->expects($this->once())->method('queryCardsByBoards')->with($boardIds)->willReturn($query);
		$query->expects($this->once())->method('select')->with('c.id')->willReturnSelf();
		$query->expects($this->once())->method('executeQuery')->willReturn($result);
		$result->expects($this->once())->method('fetchFirstColumn')->willReturn($cardIds);
		$result->expects($this->once())->method('closeCursor');
	}

	public function testActivityScopesContainBoardsAndTheirCards(): void {
		$this->expectCardIds([7, 8], [70, 71, 80]);

		$scopes = $this->provider->getActivityScopes([
			$this->resource('7'),
			$this->resource('8'),
			$this->resource('not-a-board-id'),
		], 'alice');

		self::assertCount(2, $scopes);
		self::assertSame('deck_board', $scopes[0]->getObjectType());
		self::assertSame([7, 8], $scopes[0]->getObjectIds());
		self::assertSame('deck_card', $scopes[1]->getObjectType());
		self::assertSame([70, 71, 80], $scopes[1]->getObjectIds());
	}

	public function testActivityScopesWithoutResources(): void {
		$this->cardMapper->expects($this->never())->method('queryCardsByBoards');

		self::assertSame([], $this->provider->getActivityScopes([], 'alice'));
	}

	public function testActivityScopesIgnoreInvalidBoardIds(): void {
		$this->cardMapper->expects($this->never())->method('queryCardsByBoards');

		$scopes = $this->provider->getActivityScopes([
			$this->resource('0'),
			$this->resource('abc'),
			$this->resource('-1'),
			$this->resource(''),
		], 'alice');

		self::assertSame([], $scopes);
	}

	public function testActivityScopesDeduplicateBoardIds(): void {
		$this->expectCardIds([7], [70]);

		$scopes = $this->provider->getActivityScopes([
			$this->resource('7'),
			$this->resource('7'),
		], 'alice');

		self::assertSame([7], $scopes[0]->getObjectIds());
	}

	public function testActivityScopesForBoardWithoutCards(): void {
		$this->expectCardIds([7], []);

		$scopes = $this->provider->getActivityScopes([$this->resource('7')], 'alice');

		self::assertCount(2, $scopes);
		self::assertSame([7], $scopes[0]->getObjectIds());
		self::assertSame('deck_card', $scopes[1]->getObjectType());
		self::assertSame([], $scopes[1]->getObjectIds());
	}
}
