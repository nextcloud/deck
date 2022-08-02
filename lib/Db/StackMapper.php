<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class StackMapper extends DeckMapper implements IPermissionMapper {
	private $cardMapper;

	public function __construct(IDBConnection $db, CardMapper $cardMapper) {
		parent::__construct($db, 'deck_stacks', Stack::class);
		$this->cardMapper = $cardMapper;
	}


	/**
	 * @param numeric $id
	 * @return Stack
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find($id): Stack {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param $cardId
	 * @return Stack|null
	 * @throws \OCP\DB\Exception
	 */
	public function findStackFromCardId($cardId): ?Stack {
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
	 * @param numeric $boardId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Stack[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll($boardId, $limit = null, $offset = null): array {
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
	 * @param numeric $boardId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Stack[]
	 * @throws \OCP\DB\Exception
	 */
	public function findDeleted($boardId, $limit = null, $offset = null) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->setFirstResult($offset)
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws \OCP\DB\Exception
	 */
	public function delete(Entity $entity): Entity {
		// delete cards on stack
		$this->cardMapper->deleteByStack($entity->getId());
		return parent::delete($entity);
	}

	/**
	 * @param numeric $userId
	 * @param numeric $stackId
	 * @return bool
	 * @throws \OCP\DB\Exception
	 */
	public function isOwner($userId, $id): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.id')
			->from($this->getTableName(), 's')
			->innerJoin('s', 'deck_boards', 'b', 'b.id = s.board_id')
			->where($qb->expr()->eq('s.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		return count($qb->executeQuery()->fetchAll()) > 0;
	}

	/**
	 * @param numeric $id
	 * @return int|null
	 * @throws \OCP\DB\Exception
	 */
	public function findBoardId($id): ?int {
		try {
			$entity = $this->find($id);
			return $entity->getBoardId();
		} catch (DoesNotExistException $e) {
		} catch (MultipleObjectsReturnedException $e) {
		}
		return null;
	}
}
