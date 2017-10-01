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
use OCP\IUserManager;


class AssignedUsersMapper extends DeckMapper implements IPermissionMapper {

	private $cardMapper;
	private $userManager;

	public function __construct(IDBConnection $db, CardMapper $cardMapper, IUserManager $userManager) {
		parent::__construct($db, 'deck_assigned_users', '\OCA\Deck\Db\AssignedUsers');
		$this->cardMapper = $cardMapper;
		$this->userManager = $userManager;
	}

	public function find($cardId) {
		$sql = 'SELECT * FROM `*PREFIX*deck_assigned_users` ' .
			'WHERE `card_id` = ?';
		$users = $this->findEntities($sql, [$cardId]);
		foreach ($users as &$user) {
			$this->mapParticipant($user);
		}
		return $users;
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
		$user = $this->userManager->get($entity->getParticipant());
		if ($user !== null) {
			/** @var AssignedUsers $assignment */
			$assignment = parent::insert($entity);
			$this->mapParticipant($assignment);
			return $assignment;
		} else {
			return null;
		}
	}

	public function mapParticipant(AssignedUsers &$assignment) {
		$userManager = $this->userManager;
		$assignment->resolveRelation('participant', function() use (&$userManager, &$assignment) {
			$user = $userManager->get($assignment->getParticipant());
			if($user !== null) {
				return new User($user);
			}
			return null;
		});
	}


}