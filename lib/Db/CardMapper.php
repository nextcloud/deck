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
use OCP\Notification\IManager;


class CardMapper extends DeckMapper implements IPermissionMapper {

	/** @var LabelMapper */
	private $labelMapper;
	/** @var IUserManager */
	private $userManager;
	/** @var IManager */
	private $notificationManager;
	private $databaseType;
	private $database4ByteSupport;

	public function __construct(
		IDBConnection $db,
		LabelMapper $labelMapper,
		IUserManager $userManager,
		IManager $notificationManager,
		$databaseType = 'sqlite',
		$database4ByteSupport = true
	) {
		parent::__construct($db, 'deck_cards', Card::class);
		$this->labelMapper = $labelMapper;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
		$this->databaseType = $databaseType;
		$this->database4ByteSupport = $database4ByteSupport;
	}

	public function insert(Entity $entity) {
		$entity->setDatabaseType($this->databaseType);
		$entity->setCreatedAt(time());
		$entity->setLastModified(time());
		if (!$this->database4ByteSupport) {
			$description = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $entity->getDescription());
			$entity->setDescription($description);
		}
		return parent::insert($entity);
	}

	public function update(Entity $entity, $updateModified = true) {
		if (!$this->database4ByteSupport) {
			$description = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $entity->getDescription());
			$entity->setDescription($description);
		}
		$entity->setDatabaseType($this->databaseType);

		if ($updateModified) {
			$entity->setLastModified(time());
		}

		// make sure we only reset the notification flag if the duedate changes
		if (in_array('duedate', $entity->getUpdatedFields(), true)) {
			$existing = $this->find($entity->getId());
			if ($existing->getDuedate() !== $entity->getDuedate()) {
				$entity->setNotified(false);
			}
			// remove pending notifications
			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp('deck')
				->setObject('card', $entity->getId());
			$this->notificationManager->markProcessed($notification);
		}
		return parent::update($entity);
	}

	public function markNotified(Card $card) {
		$cardUpdate = new Card();
		$cardUpdate->setId($card->getId());
		$cardUpdate->setNotified(true);
		return parent::update($cardUpdate);
	}

	/**
	 * @param $id
	 * @return RelationalEntity if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function find($id) {
		$sql = 'SELECT * FROM `*PREFIX*deck_cards` ' .
			'WHERE `id` = ?';
		/** @var Card $card */
		$card = $this->findEntity($sql, [$id]);
		$labels = $this->labelMapper->findAssignedLabelsForCard($card->id);
		$card->setLabels($labels);
		$this->mapOwner($card);
		return $card;
	}

	public function findAll($stackId, $limit = null, $offset = null) {
		$sql = 'SELECT * FROM `*PREFIX*deck_cards` 
          WHERE `stack_id` = ? AND NOT archived ORDER BY `order`';
		return $this->findEntities($sql, [$stackId], $limit, $offset);
	}

	public function findDeleted($boardId, $limit = null, $offset = null) {
		$sql = 'SELECT c.* FROM `*PREFIX*deck_cards` c
	  INNER JOIN `*PREFIX*deck_stacks` s ON s.id = c.stack_id
	  WHERE `s`.`board_id` = ? AND NOT c.archived AND NOT c.deleted_at = 0 AND c.deleted_at <= ? ORDER BY `c`.`order`';
		return $this->findEntities($sql, [$boardId, time()], $limit, $offset);
	}

	public function findAllArchived($stackId, $limit = null, $offset = null) {
		$sql = 'SELECT * FROM `*PREFIX*deck_cards` WHERE `stack_id`=? AND archived ORDER BY `last_modified`';
		return $this->findEntities($sql, [$stackId], $limit, $offset);
	}

	public function findAllByStack($stackId, $limit = null, $offset = null) {
		$sql = 'SELECT id FROM `*PREFIX*deck_cards` 
          WHERE `stack_id` = ?';
		return $this->findEntities($sql, [$stackId], $limit, $offset);
	}

	public function findOverdue() {
		$sql = 'SELECT id,title,duedate,notified from `*PREFIX*deck_cards` WHERE duedate < NOW() AND NOT archived';
		return $this->findEntities($sql);
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
			if ($user !== null) {
				return new User($user);
			}
			return null;
		});
	}


}
