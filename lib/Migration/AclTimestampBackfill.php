<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class AclTimestampBackfill implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function getName(): string {
		return 'Backfill board last_modified into deck_board_acl created_at / last_modified_at';
	}

	public function run(IOutput $output): void {
		$selectQb = $this->db->getQueryBuilder();
		$selectQb->select('a.id AS acl_id', 'b.last_modified AS board_last_modified')
			->from('deck_board_acl', 'a')
			->join('a', 'deck_boards', 'b', $selectQb->expr()->eq('b.id', 'a.board_id'))
			->where($selectQb->expr()->eq('a.created_at', $selectQb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

		$result = $selectQb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		if ($rows === []) {
			$output->info('AclTimestampBackfill: no rows to update');
			return;
		}

		$updateQb = $this->db->getQueryBuilder();
		$updateQb->update('deck_board_acl')
			->set('created_at', $updateQb->createParameter('ts'))
			->set('last_modified_at', $updateQb->createParameter('ts'))
			->where($updateQb->expr()->eq('id', $updateQb->createParameter('acl_id')));

		$now = time();
		$updated = 0;
		foreach ($rows as $row) {
			$timestamp = ((int)$row['board_last_modified'] > 0) ? (int)$row['board_last_modified'] : $now;
			$updateQb->setParameter('ts', $timestamp, IQueryBuilder::PARAM_INT);
			$updateQb->setParameter('acl_id', (int)$row['acl_id'], IQueryBuilder::PARAM_INT);
			$updateQb->executeStatement();
			$updated++;
		}

		$output->info('AclTimestampBackfill: updated ' . $updated . ' row(s)');
	}
}
