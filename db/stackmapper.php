<?php

namespace OCA\Deck\Db;

use OCP\IDb;
use OCP\AppFramework\Db\Mapper;


class StackMapper extends Mapper {

    public function __construct(IDb $db) {
        parent::__construct($db, 'deck_stacks', '\OCA\Deck\Db\Stack');
    }


    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*deck_stacks` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }


    public function findAll($boardId, $limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*deck_stacks` WHERE `board_id` = ?  ORDER BY `order`';
        return $this->findEntities($sql, [$boardId], $limit, $offset);
    }


}