<?php

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;
use OCP\IDb;
use OCP\AppFramework\Db\Mapper;



class CardMapper extends Mapper {

    private $labelMapper;

    public function __construct(IDb $db, LabelMapper $labelMapper) {
        parent::__construct($db, 'deck_cards', '\OCA\Deck\Db\Card');
        $this->labelMapper = $labelMapper;
    }
    
    public function insert(Entity $entity) {
        $entity->setCreatedAt(time());
        $entity->setLastModified(time());
        return parent::insert($entity);
    }

    /**
     * @param Entity $entity
     * @return Entity
     */
    public function update(Entity $entity) {
        $entity->setLastModified(time());
        return parent::update($entity);
    }


    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
     */
    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*deck_cards` ' .
            'WHERE `id` = ?';
        $card = $this->findEntity($sql, [$id]);
        $labels = $this->labelMapper->findAssignedLabelsForCard($card->id);
        $card->setLabels($labels);
        return $card;
    }

    public function findAllByBoard($boardId, $limit=null, $offset=null) {

    }

    public function findAll($stackId, $limit=null, $offset=null) {
        // TODO: Exclude fields like text
        $sql = 'SELECT * FROM `*PREFIX*deck_cards` 
          WHERE `stack_id` = ? AND NOT archived ORDER BY `order`';
        $entities = $this->findEntities($sql, [$stackId], $limit, $offset);
        return $entities;
    }

    // TODO: test
    public function findAllArchived($stackId, $limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*deck_cards` WHERE `stack_id`=? AND archived ORDER BY `last_modified`';
        $entities = $this->findEntities($sql, [$stackId], $limit, $offset);
        return $entities;
    }

    public function delete(Entity $entity) {
        // FIXME: delete linked elements, because owncloud doesn't support foreign keys for apps
        return parent::delete($entity);
    }

    public function assignLabel($card, $label) {
        $sql = 'INSERT INTO `*PREFIX*deck_assigned_labels` (`label_id`,`card_id`) VALUES (?,?)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(1, $label,  \PDO::PARAM_INT);
        $stmt->bindParam(2, $card,  \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function removeLabel($card, $label) {
        $sql = 'DELETE FROM `*PREFIX*deck_assigned_labels` WHERE card_id = ? AND label_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(1, $card,  \PDO::PARAM_INT);
        $stmt->bindParam(2, $label,  \PDO::PARAM_INT);
        $stmt->execute();
    }


}