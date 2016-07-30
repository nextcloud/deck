<?php

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;
use OCP\IDb;
use OCP\AppFramework\Db\Mapper;


class StackMapper extends Mapper {

    private $cardMapper;

    public function __construct(IDb $db, CardMapper $cardMapper) {
        parent::__construct($db, 'deck_stacks', '\OCA\Deck\Db\Stack');
        $this->cardMapper = $cardMapper;
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
    

    public function delete(Entity $entity) {
        // FIXME: delete linked elements, because owncloud doesn't support foreign keys for apps
        return parent::delete($entity);
    }
}