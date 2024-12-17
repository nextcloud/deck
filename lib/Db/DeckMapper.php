<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use Generator;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * @template T of Entity
 * @template-extends QBMapper<T>
 */
abstract class DeckMapper extends QBMapper {

	/**
	 * @param $id
	 * @return T
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function find($id) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * Helper function to split passed array into chunks of 1000 elements and
	 * call a given callback for fetching query results
	 *
	 * Can be useful to limit to 1000 results per query for oracle compatiblity
	 * but still iterate over all results
	 */
	public function chunkQuery(array $ids, callable $callback): Generator {
		$limit = 1000;
		while (!empty($ids)) {
			$slice = array_splice($ids, 0, $limit);
			foreach ($callback($slice) as $item) {
				yield $item;
			}
		}
	}
}
