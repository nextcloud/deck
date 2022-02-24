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
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AclMapper extends QBMapper implements IPermissionMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'deck_board_acl', Acl::class);
	}

	public function find($id): Acl {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($id)));

		return $this->findEntity($query);
	}

	public function findAll($boardId, $limit = null, $offset = null) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('board_id', $query->createNamedParameter($boardId)))
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($query);
	}

	public function isOwner($userId, $aclId): bool {
		$query = $this->db->getQueryBuilder();
		$query->select('owner')
			->from('deck_boards', 'b')
			->innerJoin('b', $this->getTableName(), 'a', $query->expr()->eq('b.id', 'a.board_id'))
			->where($query->expr()->eq('a.id', $query->createNamedParameter($aclId)));

		$cursor = $query->execute();
		$row = $cursor->fetch();
		$cursor->closeCursor();

		return is_array($row) && $row['owner'] === $userId;
	}

	public function findBoardId($id): ?int {
		try {
			$entity = $this->find($id);
			return $entity->getBoardId();
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
		}
		return null;
	}

	public function findByParticipant(int $type, string $participant): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('type', $query->createNamedParameter($type)))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter($participant)));

		return $this->findEntities($query);
	}
}
