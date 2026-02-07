<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<StackAutomation>
 */
class StackAutomationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'deck_stack_automations', StackAutomation::class);
	}

	/**
	 * @param int $id
	 * @return StackAutomation
	 */
	public function find(int $id): StackAutomation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param int $stackId
	 * @return StackAutomation[]
	 */
	public function findByStackId(int $stackId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('stack_id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT)))
			->orderBy('order', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @param int $stackId
	 * @param string $event
	 * @return StackAutomation[]
	 */
	public function findByStackIdAndEvent(int $stackId, string $event): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('stack_id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('event', $qb->createNamedParameter($event, IQueryBuilder::PARAM_STR)))
			->orderBy('order', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @param int $stackId
	 */
	public function deleteByStackId(int $stackId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('stack_id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
