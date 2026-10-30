<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<ImportRef> */
class ImportRefMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'deck_import_refs', ImportRef::class);
	}

	public function findByRef(string $app, string $kind, int $boardId, string $ref): ?ImportRef {
		return $this->findByRefs($app, $kind, $boardId, [$ref])[$ref] ?? null;
	}

	/**
	 * @param list<string> $refs
	 * @return array<string, ImportRef> by ref
	 */
	public function findByRefs(string $app, string $kind, int $boardId, array $refs): array {
		$found = [];
		foreach (array_chunk(array_values(array_unique($refs)), 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->eq('app', $qb->createNamedParameter($app)))
				->andWhere($qb->expr()->eq('kind', $qb->createNamedParameter($kind)))
				->andWhere($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->in('ref_hash', $qb->createNamedParameter(array_map(ImportRef::hashRef(...), $chunk), IQueryBuilder::PARAM_STR_ARRAY)));
			foreach ($this->findEntities($qb) as $importRef) {
				// The hash selects the row, the stored reference confirms it
				if (in_array($importRef->getRef(), $chunk, true)) {
					$found[$importRef->getRef()] = $importRef;
				}
			}
		}
		return $found;
	}

	/**
	 * @return list<ImportRef>
	 */
	public function findAllOfKind(string $app, string $kind, int $boardId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('app', $qb->createNamedParameter($app)))
			->andWhere($qb->expr()->eq('kind', $qb->createNamedParameter($kind)))
			->andWhere($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->orderBy('id');
		return $this->findEntities($qb);
	}

	/**
	 * The reference of a board an importing app created, whichever app it was
	 */
	public function findBoard(int $boardId): ?ImportRef {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('kind', $qb->createNamedParameter(ImportRef::KIND_BOARD)))
			->andWhere($qb->expr()->eq('object_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * Active card references of a board in the order they were first written
	 *
	 * @return list<ImportRef>
	 */
	public function findCardPage(string $app, int $boardId, int $afterId, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('app', $qb->createNamedParameter($app)))
			->andWhere($qb->expr()->eq('kind', $qb->createNamedParameter(ImportRef::KIND_CARD)))
			->andWhere($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('state', $qb->createNamedParameter(ImportRef::STATE_ACTIVE, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gt('id', $qb->createNamedParameter($afterId, IQueryBuilder::PARAM_INT)))
			->orderBy('id')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}
}
