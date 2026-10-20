<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends DeckMapper<Acl> */
class AclMapper extends DeckMapper implements IPermissionMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'deck_board_acl', Acl::class);
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

	/**
	 * @return array<int, Acl[]> Acl entries grouped by card id
	 * @throws \OCP\DB\Exception
	 */
	public function findInCards(array $cardIds, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('acl.id', 'acl.board_id', 'acl.type', 'acl.participant', 'acl.permission_edit', 'acl.permission_share', 'acl.permission_manage', 'acl.created_at', 'acl.last_modified_at', 'c.id AS card_id')
			->from('deck_board_acl', 'acl')
			->innerJoin('acl', 'deck_boards', 'b', 'acl.board_id = b.id')
			->innerJoin('b', 'deck_stacks', 's', 's.board_id = b.id')
			->innerJoin('s', 'deck_cards', 'c', 'c.stack_id = s.id')
			->where($qb->expr()->in('c.id', $qb->createParameter('cardIds')))
			->setMaxResults($limit)
			->setFirstResult($offset);

		$aclsByCardId = [];
		foreach ($this->chunkQuery($cardIds, function (array $ids) use ($qb) {
			$qb->setParameter('cardIds', $ids, IQueryBuilder::PARAM_INT_ARRAY);
			return $qb->executeQuery()->fetchAll();
		}) as $row) {
			$cardId = (int)$row['card_id'];
			unset($row['card_id']);
			$aclsByCardId[$cardId][] = Acl::fromRow($row);
		}
		return $aclsByCardId;
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
