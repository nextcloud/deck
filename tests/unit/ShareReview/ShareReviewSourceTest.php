<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Tests\Unit\ShareReview;

use OCA\Deck\ShareReview\ShareReviewSource;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareReviewSourceTest extends TestCase {
	private MockObject $db;
	private MockObject $logger;
	private ShareReviewSource $source;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->source = new ShareReviewSource($this->db, $this->logger);
	}

	private function makeResult(array $rows): MockObject {
		$result = $this->createMock(IResult::class);
		$result->method('fetchAll')->willReturn($rows);
		$result->method('closeCursor')->willReturn(true);
		return $result;
	}

	private function makeQb(array $fetchRows = [], int $statementRows = 0): MockObject {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('addSelect')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('leftJoin')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('orderBy')->willReturnSelf();
		$qb->method('delete')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn('?');
		$qb->method('createFunction')->willReturnArgument(0);
		$qb->method('expr')->willReturn($expr);
		$qb->method('executeQuery')->willReturn($this->makeResult($fetchRows));
		$qb->method('executeStatement')->willReturn($statementRows);

		return $qb;
	}

	private function makeThrowingQb(): MockObject {
		$expr = $this->createMock(IExpressionBuilder::class);

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('addSelect')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('leftJoin')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('orderBy')->willReturnSelf();
		$qb->method('delete')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn('?');
		$qb->method('createFunction')->willReturnArgument(0);
		$qb->method('expr')->willReturn($expr);
		$qb->method('executeQuery')->willThrowException($this->createMock(Exception::class));
		$qb->method('executeStatement')->willThrowException($this->createMock(Exception::class));
		return $qb;
	}

	/** @param array<string, mixed> $overrides */
	private function makeShareRow(array $overrides = []): array {
		return array_merge([
			'id' => 1,
			'type' => 0,
			'participant' => 'bob',
			'board_title' => 'My Board',
			'board_owner' => 'alice',
			'permission_edit' => 0,
			'permission_share' => 0,
			'permission_manage' => 0,
		], $overrides);
	}

	public function testGetName(): void {
		$this->assertSame('Deck', $this->source->getName());
	}

	public function testGetSharesEmpty(): void {
		$this->db->method('getQueryBuilder')->willReturn($this->makeQb());

		$this->assertSame([], $this->source->getShares());
	}

	public function testGetSharesUserShare(): void {
		$this->db->method('getQueryBuilder')->willReturn($this->makeQb([$this->makeShareRow()]));

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$share = $shares[0];
		$this->assertSame(1, $share['id']);
		$this->assertSame('Deck', $share['app']);
		$this->assertSame('My Board (Board)', $share['object']);
		$this->assertSame('alice', $share['initiator']);
		$this->assertSame(IShare::TYPE_USER, $share['type']);
		$this->assertSame('bob', $share['recipient']);
		$this->assertSame(Constants::PERMISSION_READ, $share['permissions']);
		$this->assertFalse($share['password']);
		$this->assertSame('1970-01-01 01:00:00', $share['time']);
		$this->assertSame('', $share['action']);
	}

	public function testGetSharesGroupShare(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow(['type' => 1, 'participant' => 'developers'])])
		);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame(IShare::TYPE_GROUP, $shares[0]['type']);
		$this->assertSame('developers', $shares[0]['recipient']);
	}

	public function testGetSharesCircleShare(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow(['type' => 7, 'participant' => 'circle-uid'])])
		);

		$shares = $this->source->getShares();

		$this->assertSame(IShare::TYPE_CIRCLE, $shares[0]['type']);
	}

	public function testGetSharesRemoteShare(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow(['type' => 6, 'participant' => 'user@remote.example'])])
		);

		$shares = $this->source->getShares();

		$this->assertSame(IShare::TYPE_REMOTE, $shares[0]['type']);
	}

	public function testGetSharesMissingBoardFallback(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow(['id' => 42, 'board_title' => null, 'board_owner' => null])])
		);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame('Board 42 (Board)', $shares[0]['object']);
	}

	public function testGetSharesReturnsEmptyOnDbException(): void {
		$this->db->method('getQueryBuilder')->willReturn($this->makeThrowingQb());
		$this->logger->expects($this->once())->method('error');

		$this->assertSame([], $this->source->getShares());
	}

	public function testComputePermissionsAllFalse(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow([
				'permission_edit' => 0,
				'permission_share' => 0,
				'permission_manage' => 0,
			])])
		);

		$shares = $this->source->getShares();

		$this->assertSame(Constants::PERMISSION_READ, $shares[0]['permissions']);
	}

	public function testComputePermissionsEditFlag(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow(['permission_edit' => 1])])
		);

		$shares = $this->source->getShares();

		$expected = Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE | Constants::PERMISSION_DELETE;
		$this->assertSame($expected, $shares[0]['permissions']);
	}

	public function testComputePermissionsShareFlag(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow(['permission_share' => 1])])
		);

		$shares = $this->source->getShares();

		$expected = Constants::PERMISSION_READ | Constants::PERMISSION_SHARE;
		$this->assertSame($expected, $shares[0]['permissions']);
	}

	public function testComputePermissionsManageFlag(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow(['permission_manage' => 1])])
		);

		$shares = $this->source->getShares();

		$expected = Constants::PERMISSION_READ | 32;
		$this->assertSame($expected, $shares[0]['permissions']);
	}

	public function testComputePermissionsAllTrue(): void {
		$this->db->method('getQueryBuilder')->willReturn(
			$this->makeQb([$this->makeShareRow([
				'permission_edit' => 1,
				'permission_share' => 1,
				'permission_manage' => 1,
			])])
		);

		$shares = $this->source->getShares();

		$expected = Constants::PERMISSION_READ
			| Constants::PERMISSION_UPDATE
			| Constants::PERMISSION_CREATE
			| Constants::PERMISSION_DELETE
			| Constants::PERMISSION_SHARE
			| 32;
		$this->assertSame($expected, $shares[0]['permissions']);
	}

	public function testDeleteShareSuccess(): void {
		$this->db->method('getQueryBuilder')->willReturn($this->makeQb([], 1));
		$this->logger->expects($this->once())->method('info');

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareNotFound(): void {
		$this->db->method('getQueryBuilder')->willReturn($this->makeQb([], 0));
		$this->logger->expects($this->once())->method('info');

		$this->assertFalse($this->source->deleteShare('99'));
	}

	public function testDeleteShareReturnsFalseOnDbException(): void {
		$this->db->method('getQueryBuilder')->willReturn($this->makeThrowingQb());
		$this->logger->expects($this->once())->method('info');
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($this->source->deleteShare('7'));
	}
}
