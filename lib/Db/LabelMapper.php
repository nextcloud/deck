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

class LabelMapper extends DeckMapper implements IPermissionMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'deck_labels', Label::class);
	}

	/**
	 * @param numeric $boardId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Label[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll($boardId, $limit = null, $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws \OCP\DB\Exception
	 */
	public function delete(Entity $entity): Entity {
		// delete assigned labels
		$this->deleteLabelAssignments($entity->getId());
		// delete label
		return parent::delete($entity);
	}

	/**
	 * @param numeric $cardId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Label[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAssignedLabelsForCard($cardId, $limit = null, $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('l.*', 'card_id')
			->from($this->getTableName(), 'l')
			->innerJoin('l', 'deck_assigned_labels', 'al', 'l.id = al.label_id')
			->where($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
			->orderBy('l.id')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	/**
	 * @param numeric $boardId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Label[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAssignedLabelsForBoard($boardId, $limit = null, $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('l.id as id', 'l.title as title', 'l.color as color')
			->selectAlias('c.id', 'card_id')
			->from($this->getTableName(), 'l')
			->innerJoin('l', 'deck_assigned_labels', 'al', 'al.label_id = l.id')
			->innerJoin('l', 'deck_cards', 'c', 'al.card_id = c.id')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->orderBy('l.id')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws \OCP\DB\Exception
	 */
	public function insert(Entity $entity): Entity {
		$entity->setLastModified(time());
		return parent::insert($entity);
	}

	/**
	 * @param Entity $entity
	 * @param bool $updateModified
	 * @return Entity
	 * @throws \OCP\DB\Exception
	 */
	public function update(Entity $entity, $updateModified = true): Entity {
		if ($updateModified) {
			$entity->setLastModified(time());
		}
		return parent::update($entity);
	}

	/**
	 * @param numeric $boardId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getAssignedLabelsForBoard($boardId) {
		$labels = $this->findAssignedLabelsForBoard($boardId);
		$result = [];
		foreach ($labels as $label) {
			if (!array_key_exists($label->getCardId(), $result)) {
				$result[$label->getCardId()] = [];
			}
			$result[$label->getCardId()][] = $label;
		}
		return $result;
	}

	/**
	 * @param numeric $labelId
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function deleteLabelAssignments($labelId) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_assigned_labels')
			->where($qb->expr()->eq('label_id', $qb->createNamedParameter($labelId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @param numeric $cardId
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	public function deleteLabelAssignmentsForCard($cardId) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_assigned_labels')
			->where($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @param string $userId
	 * @param numeric $labelId
	 * @return bool
	 * @throws \OCP\DB\Exception
	 */
	public function isOwner($userId, $labelId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('l.id')
			->from($this->getTableName(), 'l')
			->innerJoin('l', 'deck_boards', 'b', 'l.board_id = b.id')
			->where($qb->expr()->eq('l.id', $qb->createNamedParameter($labelId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('b.owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

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
		} catch (DoesNotExistException $e) {
		} catch (MultipleObjectsReturnedException $e) {
		}
		return null;
	}
}
