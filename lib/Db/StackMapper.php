<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Cache\CappedMemoryCache;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;

/** @template-extends DeckMapper<Stack> */
class StackMapper extends DeckMapper implements IPermissionMapper {
	private CappedMemoryCache $stackCache;
	private CardMapper $cardMapper;
	private ICache $cache;

	public function __construct(
		IDBConnection $db,
		CardMapper $cardMapper,
		ICacheFactory $cacheFactory,
	) {
		parent::__construct($db, 'deck_stacks', Stack::class);
		$this->cardMapper = $cardMapper;
		$this->stackCache = new CappedMemoryCache();
		$this->cache = $cacheFactory->createDistributed('deck-stackMapper');
	}


	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find(int $id): Stack {
		if (isset($this->stackCache[(string)$id])) {
			return $this->stackCache[(string)$id];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$this->stackCache[(string)$id] = $this->findEntity($qb);
		return $this->stackCache[(string)$id];
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function findStackFromCardId(int $cardId): ?Stack {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.*')
			->from($this->getTableName(), 's')
			->innerJoin('s', 'deck_cards', 'c', 's.id = c.stack_id')
			->where($qb->expr()->eq('c.id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)));

		try {
			return $this->findEntity($qb);
		} catch (MultipleObjectsReturnedException|DoesNotExistException $e) {
		}

		return null;
	}

	/**
	 * @return Stack[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll(int $boardId, ?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->setFirstResult($offset)
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function findDeleted(int $boardId, ?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->setFirstResult($offset)
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	public function update(Entity $entity): Entity {
		$result = parent::update($entity);
		$this->stackCache[(string)$entity->getId()] = $result;
		return $result;
	}

	public function delete(Entity $entity): Entity {
		// delete cards on stack
		$this->cardMapper->deleteByStack($entity->getId());
		unset($this->stackCache[(string)$entity->getId()]);
		return parent::delete($entity);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function isOwner(string $userId, int $id): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.id')
			->from($this->getTableName(), 's')
			->innerJoin('s', 'deck_boards', 'b', 'b.id = s.board_id')
			->where($qb->expr()->eq('s.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		return count($qb->executeQuery()->fetchAll()) > 0;
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function findBoardId(int $id): ?int {
		$result = $this->cache->get('findBoardId:' . $id);
		if ($result !== null) {
			return $result !== false ? $result : null;
		}
		try {
			$entity = $this->find($id);
			$result = $entity->getBoardId();
		} catch (DoesNotExistException $e) {
			$result = false;
		} catch (MultipleObjectsReturnedException $e) {
		}
		$this->cache->set('findBoardId:' . $id, $result);

		return $result !== false ? $result : null;
	}

	/**
	 * @return array<Stack>
	 * @throws \OCP\DB\Exception
	 */
	public function findToDelete(): array {
		// add buffer of 5 min
		$timeLimit = time() - (60 * 5);
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->neq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('deleted_at', $qb->createNamedParameter($timeLimit, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}
}
