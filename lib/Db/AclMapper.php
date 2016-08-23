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

use OCA\Deck\NoPermissionException;
use OCP\AppFramework\Db\Entity;
use OCP\IDb;
use OCP\AppFramework\Db\Mapper;


class AclMapper extends DeckMapper implements IPermissionMapper {

    public function __construct(IDb $db) {
        parent::__construct($db, 'deck_board_acl', '\OCA\Deck\Db\Acl');
    }

    public function findAll($boardId, $limit=null, $offset=null) {
        $sql = 'SELECT id, board_id, type, participant, permission_write, permission_invite, permission_manage FROM `*PREFIX*deck_board_acl` WHERE `board_id` = ? ';
            //'UNION SELECT 0, id, \'user\', owner, 1, 1, 1, 1 FROM `*PREFIX*deck_boards` WHERE `id` = ? ';
        return $this->findEntities($sql, [$boardId], $limit, $offset);
    }

    public function findAllShared($boardId) {
        $sql = 'SELECT id, board_id, type, participant, permission_write, permission_invite, permission_manage FROM `*PREFIX*deck_board_acl` WHERE `board_id` = ? ';
        return $this->findEntities($sql, [$boardId]);
    }
    public function findAllForCard($cardId, $userId) {
        $findBoardId = "(SELECT board_id from oc_deck_stacks WHERE id IN (SELECT stack_id from oc_deck_cards WHERE id = 15))";
        $sql = "SELECT 0, id, 'user', owner, 1, 1, 1, 1 as owner FROM `oc_deck_boards` " .
            "WHERE `id` IN (SELECT board_id from oc_deck_stacks WHERE id IN (SELECT stack_id from oc_deck_cards WHERE id = 15))
UNION
SELECT id, board_id, type, participant, permission_write, permission_invite, permission_manage, 0 FROM oc_deck_board_acl 
WHERE participant = 'admin' AND board_id IN (SELECT board_id from oc_deck_stacks WHERE id IN (SELECT stack_id from oc_deck_cards WHERE id = 15));";

    }

    public function isOwner($userId, $aclId) {
        $sql = 'SELECT * FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_board_acl` WHERE id = ?)';
        $stmt = $this->execute($sql, [$aclId]);
        $row = $stmt->fetch();
        return ($row['owner'] === $userId);
    }

    public function findBoardId($aclId) {
        $entity = $this->find($aclId);
        return $entity->getBoardId();
    }

}
