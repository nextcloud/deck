<?php
/**
 * @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @author Jakob Röhrl <jakob.roehrl@web.de>
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

class AssignedLabelsMapper extends DeckMapper  {
	private $cardMapper;
	private $userManager;
	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	public function __construct(IDBConnection $db, CardMapper $cardMapper, IUserManager $userManager, IGroupManager $groupManager) {
		parent::__construct($db, 'deck_assigned_labels', Labels::class);
		$this->cardMapper = $cardMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	/**
	 *
	 * @param $cardId
	 * @return array|Entity
	 */
	public function find($cardId) {
		$sql = 'SELECT l.*,card_id FROM `*PREFIX*deck_assigned_labels` as al ' . 
			'INNER JOIN `*PREFIX*deck_labels` as l ON l.id = al.label_id ' . 
			'WHERE `card_id` = ?';

		$labels = $this->findEntities($sql, [$cardId]);
		return $labels;
	}

}
