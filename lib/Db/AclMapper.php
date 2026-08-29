<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewQuery;

/** @template-extends DeckMapper<Acl> */
class AclMapper extends DeckMapper implements IPermissionMapper {
	public const TABLE_NAME = 'deck_board_acl';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME, Acl::class);
	}

	public function findByAccessToken(string $accessToken) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'board_id', 'type', 'participant', 'permission_edit', 'permission_share', 'permission_manage', 'token', 'created_at', 'last_modified_at')
			->from('deck_board_acl')
			->where($qb->expr()->eq('token', $qb->createNamedParameter($accessToken, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @return Acl[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll(int $boardId, ?int $limit = null, ?int $offset = null) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'board_id', 'type', 'participant', 'permission_edit', 'permission_share', 'permission_manage', 'token', 'created_at', 'last_modified_at')
			->from('deck_board_acl')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function findIn(array $boardIds, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'board_id', 'type', 'participant', 'permission_edit', 'permission_share', 'permission_manage', 'created_at', 'last_modified_at')
			->from('deck_board_acl')
			->where($qb->expr()->in('board_id', $qb->createParameter('boardIds')))
			->setMaxResults($limit)
			->setFirstResult($offset);

		return iterator_to_array($this->chunkQuery($boardIds, function (array $ids) use ($qb) {
			$qb->setParameter('boardIds', $ids, IQueryBuilder::PARAM_INT_ARRAY);
			return $this->findEntities($qb);
		}));
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function isOwner(string $userId, int $id): bool {
		$aclId = $id;
		$qb = $this->db->getQueryBuilder();
		$qb->select('acl.id')
			->from($this->getTableName(), 'acl')
			->innerJoin('acl', 'deck_boards', 'b', 'acl.board_id = b.id')
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('acl.id', $qb->createNamedParameter($aclId, IQueryBuilder::PARAM_INT)));

		return count($qb->executeQuery()->fetchAll()) > 0;
	}

	public function findBoardId(int $id): ?int {
		try {
			$entity = $this->find($id);
			return $entity->getBoardId();
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
		}
		return null;
	}

	/**
	 * @param int $type
	 * @param string $participant
	 * @return Acl[]
	 * @throws \OCP\DB\Exception
	 */
	public function findByParticipant(int $type, string $participant): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant', $qb->createNamedParameter($participant, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($qb);
	}

	public function findParticipantFromBoard(int $boardId, int $type, string $participant): ?Acl {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_board_acl')
			->where($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT)))
			->where($qb->expr()->eq('participant', $qb->createNamedParameter($participant, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function deleteParticipantFromBoard(int $boardId, int $type, string $participant): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_board_acl')
			->where($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant', $qb->createNamedParameter($participant, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function findByType(int $type): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_board_acl')
			->where($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * Sort fields of the share-review contract mapped to their column. The time
	 * sort is an expression (see shareReviewTimeExpression()) and resolved
	 * separately; user input never reaches the query other than through this
	 * whitelist and bound parameters.
	 */
	private const SHARE_REVIEW_SORT_COLUMNS = [
		ShareReviewQuery::SORT_OBJECT => 'b.title',
		ShareReviewQuery::SORT_INITIATOR => 'b.owner',
		ShareReviewQuery::SORT_RECIPIENT => 'a.participant',
		ShareReviewQuery::SORT_TYPE => 'a.type',
	];

	/**
	 * Fetch one page of ACL rows with their board title and owner for
	 * ShareReview, sorted, searched and filtered as the query demands.
	 *
	 * @param list<int>|null $participantTypes native Acl::PERMISSION_TYPE_* values
	 *                                         the row must have one of; null = no
	 *                                         type filter, [] = nothing matches
	 * @param list<string>|null $permissionColumns permission columns
	 *                                             (permission_edit/share/manage) the
	 *                                             row must have at least one of set;
	 *                                             null = no permission filter, [] =
	 *                                             nothing matches
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findPageForShareReview(ShareReviewQuery $query, ?array $participantTypes = null, ?array $permissionColumns = null): array {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		$this->applyShareReviewFilters($qb, $query, $participantTypes, $permissionColumns);
		$this->applyShareReviewOrder($qb, $query);
		$qb->setFirstResult($query->offset)
			->setMaxResults($query->limit);
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/**
	 * Fetch one ACL row with its board title and owner for ShareReview, in
	 * the same shape as findPageForShareReview() rows.
	 *
	 * @return array<string, mixed>|null
	 * @throws Exception
	 */
	public function findForShareReview(int $id): ?array {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		// andWhere: where() would replace the trashed-board exclusion
		$qb->andWhere($qb->expr()->eq('a.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? null : $row;
	}

	/**
	 * All ACL rows with their board title and owner, in the
	 * findPageForShareReview() shape, streamed in immutable id order so the
	 * enumeration stays stable under concurrent edits and the full list is
	 * never held in memory.
	 *
	 * @return \Generator<int, array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllForShareReview(): \Generator {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		$qb->orderBy('a.id', 'ASC');
		$result = $qb->executeQuery();
		try {
			while (($row = $result->fetch()) !== false) {
				yield $row;
			}
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * Count all ACLs and the ACLs matching the query's search and filters. The
	 * filtered count is only computed when the query narrows the result.
	 *
	 * @param list<int>|null $participantTypes see findPageForShareReview()
	 * @param list<string>|null $permissionColumns see findPageForShareReview()
	 * @throws Exception
	 */
	public function countForShareReview(ShareReviewQuery $query, ?array $participantTypes = null, ?array $permissionColumns = null): ShareReviewCounts {
		$qb = $this->shareReviewQuery();
		$qb->select($qb->func()->count('a.id'));
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();
		if (!$query->isFiltered() && $participantTypes === null && $permissionColumns === null) {
			return new ShareReviewCounts($total, $total);
		}
		$qb = $this->shareReviewQuery();
		$qb->select($qb->func()->count('a.id'));
		$this->applyShareReviewFilters($qb, $query, $participantTypes, $permissionColumns);
		$result = $qb->executeQuery();
		$filtered = (int)$result->fetchOne();
		$result->closeCursor();
		return new ShareReviewCounts($total, $filtered);
	}

	/**
	 * Count the ACLs matching the query's search and filters per participant type.
	 *
	 * @param list<int>|null $participantTypes see findPageForShareReview()
	 * @param list<string>|null $permissionColumns see findPageForShareReview()
	 * @return array<int, int> native participant type to count, zero counts omitted
	 * @throws Exception
	 */
	public function countByTypeForShareReview(ShareReviewQuery $query, ?array $participantTypes = null, ?array $permissionColumns = null): array {
		$qb = $this->shareReviewQuery();
		$qb->select('a.type')
			->selectAlias($qb->func()->count('a.id'), 'share_count')
			->groupBy('a.type');
		$this->applyShareReviewFilters($qb, $query, $participantTypes, $permissionColumns);
		$result = $qb->executeQuery();
		$counts = [];
		while (($row = $result->fetch()) !== false) {
			$counts[(int)$row['type']] = (int)$row['share_count'];
		}
		$result->closeCursor();
		return $counts;
	}

	/**
	 * ACLs joined with their board, the base of every share-review query.
	 */
	private function shareReviewQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->from(self::TABLE_NAME, 'a')
			->leftJoin('a', 'deck_boards', 'b', $qb->expr()->eq('a.board_id', 'b.id'));
		// soft-deleted (trashed) boards are hidden everywhere in deck, so
		// their ACLs are not reviewable either; orphaned rows (board row
		// gone) stay visible through the NULL branch
		$qb->andWhere($qb->expr()->orX(
			$qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)),
			$qb->expr()->isNull('b.deleted_at'),
		));
		return $qb;
	}

	private function selectShareReviewColumns(IQueryBuilder $qb): void {
		$qb->select(
			'a.id', 'a.board_id', 'a.type', 'a.participant',
			'a.permission_edit', 'a.permission_share', 'a.permission_manage', 'a.created_at', 'a.last_modified_at'
		)
			->selectAlias('b.title', 'board_title')
			->selectAlias('b.owner', 'board_owner');
	}

	/**
	 * The share time as exposed to share review: last_modified_at is bumped on
	 * every insert and update; rows predating the columns keep 0 and fall back
	 * to created_at (also 0 for them — no backfill, by design).
	 */
	private function shareReviewTimeExpression(IQueryBuilder $qb): string {
		return 'COALESCE(NULLIF(' . $qb->getColumnName('a.last_modified_at') . ', 0), ' . $qb->getColumnName('a.created_at') . ')';
	}

	/**
	 * Translate the share-review query into WHERE clauses. Deck shares carry
	 * neither a password nor an expiration date, so those filters match
	 * nothing when they ask for protected/expiring shares.
	 *
	 * @param list<int>|null $participantTypes see findPageForShareReview()
	 * @param list<string>|null $permissionColumns see findPageForShareReview()
	 */
	private function applyShareReviewFilters(IQueryBuilder $qb, ShareReviewQuery $query, ?array $participantTypes, ?array $permissionColumns): void {
		$expr = $qb->expr();
		// A column that is never NULL, negated: the portable "matches nothing"
		$matchesNothing = $expr->isNull('a.id');

		if ($query->search !== null) {
			$pattern = $this->shareReviewLikePattern($qb, $query->search);
			$qb->andWhere($expr->orX(
				$expr->iLike('b.title', $pattern),
				$expr->iLike('b.owner', $pattern),
				$expr->iLike('a.participant', $pattern),
			));
		}
		if ($query->objectSearch !== null) {
			$qb->andWhere($expr->iLike('b.title', $this->shareReviewLikePattern($qb, $query->objectSearch)));
		}
		if ($query->objectSearchAny !== null) {
			$qb->andWhere($query->objectSearchAny === []
				? $matchesNothing
				: $expr->orX(...array_map(fn (string $term): string => $expr->iLike('b.title', $this->shareReviewLikePattern($qb, $term)), $query->objectSearchAny)));
		}
		$this->applyShareReviewIdentityFilter($qb, 'b.owner', $query->initiatorSearch, $query->initiatorIds);
		$this->applyShareReviewIdentityFilter($qb, 'a.participant', $query->recipientSearch, $query->recipientIds);

		if ($query->modifiedSinceTimestamp !== null) {
			$qb->andWhere($expr->gt(
				$qb->createFunction($this->shareReviewTimeExpression($qb)),
				$qb->createNamedParameter($query->modifiedSinceTimestamp, IQueryBuilder::PARAM_INT),
			));
		}
		if ($participantTypes !== null) {
			$qb->andWhere($participantTypes === []
				? $matchesNothing
				: $expr->in('a.type', $qb->createNamedParameter($participantTypes, IQueryBuilder::PARAM_INT_ARRAY)));
		}
		// board ACLs have no password, no expiration and no access token
		if ($query->hasPassword === true || $query->hasExpiration === true
			|| $query->expiresAfterTimestamp !== null || $query->expiresBeforeTimestamp !== null
			|| $query->tokens !== null) {
			$qb->andWhere($matchesNothing);
		}
		if ($permissionColumns !== null) {
			$qb->andWhere($permissionColumns === []
				? $matchesNothing
				: $expr->orX(...array_map(
					static fn (string $column): string => $expr->eq('a.' . $column, $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)),
					$permissionColumns,
				)));
		}
	}

	/**
	 * Scoped substring and exact id list on one identity column, OR-combined
	 * with each other and AND-combined with everything else.
	 *
	 * @param list<string>|null $ids
	 */
	private function applyShareReviewIdentityFilter(IQueryBuilder $qb, string $column, ?string $search, ?array $ids): void {
		$predicates = [];
		if ($search !== null) {
			$predicates[] = $qb->expr()->iLike($column, $this->shareReviewLikePattern($qb, $search));
		}
		if ($ids !== null) {
			$predicates[] = $ids === []
				? $qb->expr()->isNull('a.id')
				: $qb->expr()->in($column, $qb->createNamedParameter($ids, IQueryBuilder::PARAM_STR_ARRAY));
		}
		if ($predicates !== []) {
			$qb->andWhere($qb->expr()->orX(...$predicates));
		}
	}

	/**
	 * Case-insensitive substring pattern with the LIKE wildcards of the input
	 * escaped, bound as a parameter.
	 */
	private function shareReviewLikePattern(IQueryBuilder $qb, string $term): IParameter {
		return $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%');
	}

	/**
	 * ORDER BY through the sort whitelist, NULL board titles last in both
	 * directions (a deleted board leaves the join empty; databases disagree on
	 * the default), and the ACL id as tiebreaker in the same direction.
	 */
	private function applyShareReviewOrder(IQueryBuilder $qb, ShareReviewQuery $query): void {
		$direction = $query->sortDescending ? 'DESC' : 'ASC';
		if ($query->sortField === ShareReviewQuery::SORT_TIME) {
			$qb->orderBy($qb->createFunction($this->shareReviewTimeExpression($qb)), $direction);
		} else {
			$column = self::SHARE_REVIEW_SORT_COLUMNS[$query->sortField];
			$qb->orderBy($qb->createFunction('CASE WHEN ' . $qb->getColumnName($column) . ' IS NULL THEN 1 ELSE 0 END'), 'ASC')
				->addOrderBy($column, $direction);
		}
		$qb->addOrderBy('a.id', $direction);
	}

	public function insert(Entity $entity): Entity {
		/** @var Acl $entity */
		$now = time();
		$entity->setCreatedAt($now);
		$entity->setLastModifiedAt($now);
		return parent::insert($entity);
	}

	public function update(Entity $entity): Entity {
		/** @var Acl $entity */
		$entity->setLastModifiedAt(time());
		return parent::update($entity);
	}
}
