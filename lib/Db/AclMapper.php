<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends DeckMapper<Acl> */
class AclMapper extends DeckMapper implements IPermissionMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'deck_board_acl', Acl::class);
	}

	/**
	 * @param numeric $boardId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Acl[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll($boardId, $limit = null, $offset = null) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'board_id', 'type', 'participant', 'permission_edit', 'permission_share', 'permission_manage')
			->from('deck_board_acl')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function findIn(array $boardIds, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'board_id', 'type', 'participant', 'permission_edit', 'permission_share', 'permission_manage')
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
	 * @param numeric $userId
	 * @param numeric $id
	 * @return bool
	 * @throws \OCP\DB\Exception
	 */
	public function isOwner($userId, $id): bool {
		$aclId = $id;
		$qb = $this->db->getQueryBuilder();
		$qb->select('acl.id')
			->from($this->getTableName(), 'acl')
			->innerJoin('acl', 'deck_boards', 'b', 'acl.board_id = b.id')
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('acl.id', $qb->createNamedParameter($aclId, IQueryBuilder::PARAM_INT)));

		return count($qb->executeQuery()->fetchAll()) > 0;
	}

	/**
	 * @param numeric $id
	 * @return int|null
	 */
	public function findBoardId($id): ?int {
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
	public function findByParticipant($type, $participant): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant', $qb->createNamedParameter($participant, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($qb);
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
}
