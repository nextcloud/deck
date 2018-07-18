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

use OCP\AppFramework\Db\Entity;
use OCP\IDBConnection;


class StackMapper extends DeckMapper implements IPermissionMapper {

	private $cardMapper;

	public function __construct(IDBConnection $db, CardMapper $cardMapper) {
		parent::__construct($db, 'deck_stacks', Stack::class);
		$this->cardMapper = $cardMapper;
	}


	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function find($id) {
		$sql = 'SELECT * FROM `*PREFIX*deck_stacks` ' .
			'WHERE `id` = ?';
		return $this->findEntity($sql, [$id]);
	}


	public function findAll($boardId, $limit = null, $offset = null) {
		$sql = 'SELECT * FROM `*PREFIX*deck_stacks` WHERE `board_id` = ? AND deleted_at = 0 ORDER BY `order`';
		return $this->findEntities($sql, [$boardId], $limit, $offset);
	}


	public function findDeleted($boardId, $limit = null, $offset = null) {
	 $sql = 'SELECT * FROM `*PREFIX*deck_stacks` s
	  WHERE `s`.`board_id` = ? AND NOT s.deleted_at = 0';
	 return $this->findEntities($sql, [$boardId], $limit, $offset);
	}


	public function delete(Entity $entity) {
		// delete cards on stack
		$this->cardMapper->deleteByStack($entity->getId());
		return parent::delete($entity);
	}

	public function isOwner($userId, $stackId) {
		$sql = 'SELECT owner FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_stacks` WHERE id = ?)';
		$stmt = $this->execute($sql, [$stackId]);
		$row = $stmt->fetch();
		return ($row['owner'] === $userId);
	}

	public function findBoardId($stackId) {
		$entity = $this->find($stackId);
		return $entity->getBoardId();
	}
}
