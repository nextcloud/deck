<?php

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;
use OCP\IDb;
use OCP\AppFramework\Db\Mapper;


class LabelMapper extends DeckMapper {

    public function __construct(IDb $db) {
        parent::__construct($db, 'deck_labels', '\OCA\Deck\Db\Label');
    }

    public function findAll($boardId, $limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*deck_labels` WHERE `board_id` = ?';
        return $this->findEntities($sql, [$boardId], $limit, $offset);
    }

    public function delete(Entity $entity) {
        // FIXME: delete linked elements, because owncloud doesn't support foreign keys for apps
        return parent::delete($entity);
    }

    public function findAssignedLabelsForCard($cardId) {
        $sql = 'SELECT l.* FROM `*PREFIX*deck_assigned_labels` as al INNER JOIN *PREFIX*deck_labels as l ON l.id = al.label_id WHERE `card_id` = ?';
        return $this->findEntities($sql, [$cardId], $limit, $offset);
    }
    public function findAssignedLabelsForBoard($boardId, $limit=null, $offset=null) {
        $sql = "SELECT c.id as card_id, l.id as id, l.title as title, l.color as color FROM oc_deck_cards as c " .
            " INNER JOIN oc_deck_assigned_labels as al ON al.card_id = c.id INNER JOIN oc_deck_labels as l ON  al.label_id = l.id WHERE board_id=?";
        $entities = $this->findEntities($sql, [$boardId], $limit, $offset);
        return $entities;
    }

    public function getAssignedLabelsForBoard($boardId) {
        $labels = $this->findAssignedLabelsForBoard($boardId);
        $result = array();
        foreach ($labels as $label) {
            if(!is_array($result[$label->getCardId()])) {
                $result[$label->getCardId()] = array();
            }
            $result[$label->getCardId()][] = $label;
        }
        return $result;
    }
}
