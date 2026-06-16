<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Migration;

use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AclTimestampBackfillTest extends TestCase {

	private IDBConnection&MockObject $db;
	private IOutput&MockObject $output;
	private AclTimestampBackfill $backfill;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
		$this->output = $this->createMock(IOutput::class);
		$this->backfill = new AclTimestampBackfill($this->db);
	}

	public function testGetName(): void {
		$this->assertNotEmpty($this->backfill->getName());
	}

	public function testRunNoRowsIsNoop(): void {
		[$selectQb] = $this->buildSelectQb([]);

		$this->db->method('getQueryBuilder')->willReturn($selectQb);
		$this->output->expects($this->once())
			->method('info')
			->with($this->stringContains('no rows'));

		$this->backfill->run($this->output);
	}

	public function testRunGroupsRowsWithSameTimestampIntoOneUpdate(): void {
		// Two ACLs from the same board share the same timestamp → only 1 UPDATE
		$rows = [
			['acl_id' => 1, 'board_last_modified' => 1000000],
			['acl_id' => 2, 'board_last_modified' => 1000000],
		];
		[$selectQb] = $this->buildSelectQb($rows);
		$updateQb = $this->buildUpdateQb(1); // single IN-clause UPDATE for both IDs

		$this->db->expects($this->exactly(2)) // 1 SELECT + 1 UPDATE
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($selectQb, $updateQb);

		$this->output->expects($this->once())
			->method('info')
			->with($this->stringContains('2'));

		$this->backfill->run($this->output);
	}

	public function testRunIssuesSeparateUpdatePerDistinctTimestamp(): void {
		// Two ACLs from different boards → two distinct timestamps → 2 UPDATEs
		$rows = [
			['acl_id' => 1, 'board_last_modified' => 1000000],
			['acl_id' => 2, 'board_last_modified' => 2000000],
		];
		[$selectQb] = $this->buildSelectQb($rows);
		$updateQb1 = $this->buildUpdateQb(1);
		$updateQb2 = $this->buildUpdateQb(1);

		$this->db->expects($this->exactly(3)) // 1 SELECT + 2 UPDATEs
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($selectQb, $updateQb1, $updateQb2);

		$this->output->expects($this->once())
			->method('info')
			->with($this->stringContains('2'));

		$this->backfill->run($this->output);
	}

	public function testRunUsesBoardTimestampWhenAvailable(): void {
		$rows = [['acl_id' => 1, 'board_last_modified' => 1234567]];
		[$selectQb] = $this->buildSelectQb($rows);

		$capturedTs = null;
		$updateQb = $this->buildUpdateQb(1, function (mixed $value, int $type) use (&$capturedTs): void {
			if ($type === IQueryBuilder::PARAM_INT) {
				$capturedTs = $value;
			}
		});

		$this->db->expects($this->exactly(2))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($selectQb, $updateQb);
		$this->output->method('info');

		$this->backfill->run($this->output);

		$this->assertSame(1234567, $capturedTs);
	}

	public function testRunUsesCurrentTimeWhenBoardTimestampIsZero(): void {
		$rows = [['acl_id' => 1, 'board_last_modified' => 0]];
		[$selectQb] = $this->buildSelectQb($rows);

		$capturedTs = null;
		$before = time();
		$updateQb = $this->buildUpdateQb(1, function (mixed $value, int $type) use (&$capturedTs): void {
			if ($type === IQueryBuilder::PARAM_INT) {
				$capturedTs = $value;
			}
		});

		$this->db->expects($this->exactly(2))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($selectQb, $updateQb);
		$this->output->method('info');

		$this->backfill->run($this->output);
		$after = time();

		$this->assertGreaterThanOrEqual($before, $capturedTs);
		$this->assertLessThanOrEqual($after, $capturedTs);
	}

	/**
	 * @return array{0: IQueryBuilder&MockObject}
	 */
	private function buildSelectQb(array $rows): array {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');

		$result = $this->createMock(IResult::class);
		$result->method('fetchAll')->willReturn($rows);
		$result->expects($this->once())->method('closeCursor');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('join')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn('?');
		$qb->method('expr')->willReturn($expr);
		$qb->method('executeQuery')->willReturn($result);

		return [$qb];
	}

	private function buildUpdateQb(int $expectedExecutions, ?\Closure $onCreateNamedParameter = null): IQueryBuilder&MockObject {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('in')->willReturn('1=1');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('update')->willReturnSelf();
		$qb->method('set')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->expects($this->exactly($expectedExecutions))->method('executeStatement');

		if ($onCreateNamedParameter !== null) {
			$qb->method('createNamedParameter')->willReturnCallback(
				function (mixed $value, int $type) use ($onCreateNamedParameter): string {
					$onCreateNamedParameter($value, $type);
					return '?';
				}
			);
		} else {
			$qb->method('createNamedParameter')->willReturn('?');
		}

		return $qb;
	}
}
