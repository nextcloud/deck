<?php

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;
use OCP\IDb;
use OCP\AppFramework\Db\Mapper;


class AclMapper extends DeckMapper {

    public function __construct(IDb $db) {
        parent::__construct($db, 'deck_board_acl', '\OCA\Deck\Db\Acl');
    }

    public function findAll($boardId, $limit=null, $offset=null) {
        $sql = 'SELECT id, board_id, type, participant, permission_write, permission_invite, permission_manage, 0 as owner FROM `*PREFIX*deck_board_acl` WHERE `board_id` = ? UNION SELECT 0, id, \'user\', owner, 1, 1, 1, 1 FROM `*PREFIX*deck_boards` WHERE `id` = ? ';
        return $this->findEntities($sql, [$boardId, $boardId], $limit, $offset);
    }

    public function findAllForCard($cardId, $userId) {
        $findBoardId = "(SELECT board_id from oc_deck_stacks WHERE id IN (SELECT stack_id from oc_deck_cards WHERE id = 15))";
        $sql = "SELECT 0, id, 'user', owner, 1, 1, 1, 1 as owner FROM `oc_deck_boards` WHERE `id` IN (SELECT board_id from oc_deck_stacks WHERE id IN (SELECT stack_id from oc_deck_cards WHERE id = 15))
UNION
SELECT id, board_id, type, participant, permission_write, permission_invite, permission_manage, 0 FROM oc_deck_board_acl 
WHERE participant = 'admin' AND board_id IN (SELECT board_id from oc_deck_stacks WHERE id IN (SELECT stack_id from oc_deck_cards WHERE id = 15));";

    }


}
