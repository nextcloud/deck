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
        $sql = 'SELECT * FROM `*PREFIX*deck_board_acl` WHERE `board_id` = ?';
        return $this->findEntities($sql, [$boardId], $limit, $offset);
    }
    
}
