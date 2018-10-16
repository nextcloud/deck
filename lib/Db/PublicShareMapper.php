<?php
/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
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

use OCP\IDBConnection;

class PublicShareMapper extends DeckMapper implements IPermissionMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'deck_public_board_shares', Label::class);
	}

	public function findAll($boardId, $limit = null, $offset = null) {
		$sql = 'SELECT * FROM `*PREFIX*deck_public_board_shares` WHERE `board_id` = ? ORDER BY `id`';
        return $this->findEntities($sql, [$boardId], $limit, $offset);
	}

    public function isOwner($userId, $boardId) {
		$board = $this->find($boardId);
		return ($board->getOwner() === $userId);
	}

    public function findBoardId($publicShareId) {
		$entity = $this->find($publicShareId);
		return $entity->getBoardId();
    }

    public function delete(\OCP\AppFramework\Db\Entity $entity) {
		return parent::delete($entity);
	}

}