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
use OCP\IDb;
use OCP\AppFramework\Db\Mapper;


class LabelMapper extends DeckMapper implements IPermissionMapper {

    public function __construct(IDb $db) {
        parent::__construct($db, 'deck_labels', '\OCA\Deck\Db\Label');
    }

    public function findAll($boardId, $limit=null, $offset=null) {
        $sql = 'SELECT * FROM `*PREFIX*deck_labels` WHERE `board_id` = ? ORDER BY `id`';
        return $this->findEntities($sql, [$boardId], $limit, $offset);
    }

    public function delete(Entity $entity) {
		// delete assigned labels
		$this->deleteLabelAssignments($entity->getId());
		// delete label
        return parent::delete($entity);
    }

    public function findAssignedLabelsForCard($cardId, $limit=null, $offset=null) {
        $sql = 'SELECT l.* FROM `*PREFIX*deck_assigned_labels` as al INNER JOIN *PREFIX*deck_labels as l ON l.id = al.label_id WHERE `card_id` = ? ORDER BY l.id';
        return $this->findEntities($sql, [$cardId], $limit, $offset);
    }
    public function findAssignedLabelsForBoard($boardId, $limit=null, $offset=null) {
        $sql = "SELECT c.id as card_id, l.id as id, l.title as title, l.color as color FROM oc_deck_cards as c " .
            " INNER JOIN oc_deck_assigned_labels as al ON al.card_id = c.id INNER JOIN oc_deck_labels as l ON  al.label_id = l.id WHERE board_id=? ORDER BY l.id";
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

	public function deleteLabelAssignments($labelId, $limit=null, $offset=null) {
		$sql = 'DELETE FROM `*PREFIX*deck_assigned_labels` WHERE label_id = ?';
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam(1, $labelId,  \PDO::PARAM_INT);
		$stmt->execute();
	}

	public function deleteLabelAssignmentsForCard($cardId, $limit=null, $offset=null) {
		$sql = 'DELETE FROM `*PREFIX*deck_assigned_labels` WHERE card_id = ?';
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam(1, $cardId,  \PDO::PARAM_INT);
		$stmt->execute();
	}

    public function isOwner($userId, $labelId) {
        $sql = 'SELECT * FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_labels` WHERE id = ?)';
        $stmt = $this->execute($sql, [$labelId]);
        $row = $stmt->fetch();
        return ($row['owner'] === $userId);
    }

    public function findBoardId($labelId) {
        $entity = $this->find($labelId);
        return $entity->getBoardId();
    }
}
