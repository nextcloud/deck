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
use OCP\IDBConnection;
use OCP\IUserManager;


class CardMapper extends DeckMapper implements IPermissionMapper {

	private $labelMapper;
	private $userManager;
	private $databaseType;

	public function __construct(
		IDBConnection $db,
		LabelMapper $labelMapper,
		IUserManager $userManager,
		$databaseType = 'sqlite'
	) {
		parent::__construct($db, 'deck_cards', '\OCA\Deck\Db\Card');
		$this->labelMapper = $labelMapper;
		$this->userManager = $userManager;
		$this->databaseType = $databaseType;
	}

	public function insert(Entity $entity) {
		$entity->setDatabaseType($this->databaseType);
		$entity->setCreatedAt(time());
		$entity->setLastModified(time());
		return parent::insert($entity);
	}

	public function update(Entity $entity) {
		$entity->setDatabaseType($this->databaseType);
		$entity->setLastModified(time());
		return parent::update($entity);
	}

	/**
	 * @param $id
	 * @return RelationalEntity if not found
	 */
	public function find($id) {
		$sql = 'SELECT * FROM `*PREFIX*deck_cards` ' .
			'WHERE `id` = ?';
		$card = $this->findEntity($sql, [$id]);
		$labels = $this->labelMapper->findAssignedLabelsForCard($card->id);
		$card->setLabels($labels);
		$this->mapOwner($card);
		return $card;
	}

	public function findAll($stackId, $limit = null, $offset = null) {
		$sql = 'SELECT * FROM `*PREFIX*deck_cards` 
          WHERE `stack_id` = ? AND NOT archived ORDER BY `order`';
		$entities = $this->findEntities($sql, [$stackId], $limit, $offset);
		return $entities;
	}

	public function findAllArchived($stackId, $limit = null, $offset = null) {
		$sql = 'SELECT * FROM `*PREFIX*deck_cards` WHERE `stack_id`=? AND archived ORDER BY `last_modified`';
		$entities = $this->findEntities($sql, [$stackId], $limit, $offset);
		return $entities;
	}

	public function findAllByStack($stackId, $limit = null, $offset = null) {
		$sql = 'SELECT id FROM `*PREFIX*deck_cards` 
          WHERE `stack_id` = ?';
		$entities = $this->findEntities($sql, [$stackId], $limit, $offset);
		return $entities;
	}

	public function delete(Entity $entity) {
		// delete assigned labels
		$this->labelMapper->deleteLabelAssignmentsForCard($entity->getId());
		// delete card
		return parent::delete($entity);
	}

	public function deleteByStack($stackId) {
		$cards = $this->findAllByStack($stackId);
		foreach ($cards as $card) {
			$this->delete($card);
		}

	}

	public function assignLabel($card, $label) {
		$sql = 'INSERT INTO `*PREFIX*deck_assigned_labels` (`label_id`,`card_id`) VALUES (?,?)';
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam(1, $label, \PDO::PARAM_INT);
		$stmt->bindParam(2, $card, \PDO::PARAM_INT);
		$stmt->execute();
	}

	public function removeLabel($card, $label) {
		$sql = 'DELETE FROM `*PREFIX*deck_assigned_labels` WHERE card_id = ? AND label_id = ?';
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam(1, $card, \PDO::PARAM_INT);
		$stmt->bindParam(2, $label, \PDO::PARAM_INT);
		$stmt->execute();
	}

	public function isOwner($userId, $cardId) {
		$sql = 'SELECT owner FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_stacks` WHERE id IN (SELECT stack_id FROM `*PREFIX*deck_cards` WHERE id = ?))';
		$stmt = $this->execute($sql, [$cardId]);
		$row = $stmt->fetch();
		return ($row['owner'] === $userId);
	}

	public function findBoardId($cardId) {
		$sql = 'SELECT id FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_stacks` WHERE id IN (SELECT stack_id FROM `*PREFIX*deck_cards` WHERE id = ?))';
		$stmt = $this->execute($sql, [$cardId]);
		$row = $stmt->fetch();
		return $row['id'];
	}

	public function mapOwner(Card &$card) {
		$userManager = $this->userManager;
		$card->resolveRelation('owner', function($owner) use (&$userManager) {
			$user = $userManager->get($owner);
			if($user !== null) {
				return new User($user);
			}
			return null;
		});
	}


}
