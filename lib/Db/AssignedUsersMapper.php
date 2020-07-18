<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;

class AssignedUsersMapper extends DeckMapper implements IPermissionMapper {
	private $cardMapper;
	private $userManager;
	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	public function __construct(IDBConnection $db, CardMapper $cardMapper, IUserManager $userManager, IGroupManager $groupManager) {
		parent::__construct($db, 'deck_assigned_users', AssignedUsers::class);
		$this->cardMapper = $cardMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	/**
	 * FIXME: rename this since it returns multiple entities otherwise the naming is confusing with Entity::find
	 *
	 * @param $cardId
	 * @return array|Entity
	 */
	public function find($cardId) {
		$sql = 'SELECT * FROM `*PREFIX*deck_assigned_users` ' .
			'WHERE `card_id` = ?';
		$users = $this->findEntities($sql, [$cardId]);
		foreach ($users as &$user) {
			$this->mapParticipant($user);
		}
		return $users;
	}

	public function findByUserId($uid) {
		$sql = 'SELECT * FROM `*PREFIX*deck_assigned_users` ' .
			'WHERE `participant` = ?';
		return $this->findEntities($sql, [$uid]);
	}


	public function isOwner($userId, $cardId) {
		return $this->cardMapper->isOwner($userId, $cardId);
	}

	public function findBoardId($cardId) {
		return $this->cardMapper->findBoardId($cardId);
	}

	/**
	 * Check if user exists before assigning it to a card
	 *
	 * @param Entity $entity
	 * @return null|Entity
	 */
	public function insert(Entity $entity) {
		$origin = $this->getOrigin($entity);
		if ($origin !== null) {
			/** @var AssignedUsers $assignment */
			$assignment = parent::insert($entity);
			$this->mapParticipant($assignment);
			return $assignment;
		}
		return null;
	}

	public function mapParticipant(AssignedUsers &$assignment) {
		$self = $this;
		$assignment->resolveRelation('participant', function () use (&$self, &$assignment) {
			return $self->getOrigin($assignment);
		});
	}

	private function getOrigin(AssignedUsers $assignment) {
		if ($assignment->getType() === AssignedUsers::TYPE_USER) {
			$origin = $this->userManager->get($assignment->getParticipant());
			return $origin ? new User($origin) : null;
		}
		if ($assignment->getType() === AssignedUsers::TYPE_GROUP) {
			$origin = $this->groupManager->get($assignment->getParticipant());
			return $origin ? new Group($origin) : null;
		}
		if ($assignment->getType() === AssignedUsers::TYPE_CIRCLE) {
			$origin = $this->groupManager->get($assignment->getParticipant());
			return $origin ? new Circle($origin) : null;
		}
		return null;
	}

	/**
	 * @param $ownerId
	 * @param $newOwnerId
	 * @return void
	 */
	public function transferOwnership($ownerId, $newOwnerId) {
		$params = [
			'newOwner' => $newOwnerId,
            'type' => AssignedUsers::TYPE_USER
		];
        $sql = "DELETE FROM `{$this->tableName}`  WHERE `participant` = :newOwner AND `type`= :type";
        $stmt = $this->execute($sql, $params);
        $stmt->closeCursor();
        $params = [
            'owner' => $ownerId,
            'newOwner' => $newOwnerId,
            'type' => AssignedUsers::TYPE_USER
        ];
		$sql = "UPDATE `{$this->tableName}`  SET `participant` = :newOwner WHERE `participant` = :owner AND `type`= :type";
		$stmt = $this->execute($sql, $params);
		$stmt->closeCursor();
	}
}
