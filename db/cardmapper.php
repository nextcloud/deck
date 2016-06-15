<?php

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;
use OCP\IDb;
use OCP\AppFramework\Db\Mapper;



class CardMapper extends Mapper {

    public function __construct(IDb $db) {
        parent::__construct($db, 'deck_cards', '\OCA\Deck\Db\Card');
    }


    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*deck_cards` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }


    public function findAll($stackId, $limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*deck_cards` WHERE `stack_id` = ?  ORDER BY `order`';
        return $this->findEntities($sql, [$stackId], $limit, $offset);
    }

    public function delete(Entity $entity) {
        // FIXME: delete linked elements, because owncloud doesn't support foreign keys for apps
        return parent::delete($entity);
    }

}