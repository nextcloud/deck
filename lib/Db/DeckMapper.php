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

use OCP\AppFramework\Db\Mapper;

abstract class DeckMapper extends Mapper {

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity if not found
	 */
	public function find($id) {
		$sql = 'SELECT * FROM `' . $this->tableName . '` ' . 'WHERE `id` = ?';
		return $this->findEntity($sql, [$id]);
	}

	/**
	 * Add relational data to an Entity by calling the related Mapper
	 * @param $entities
	 * @param $entityType
	 * @param $property
	 * addRelation($cards, $labels, function($one, $many) {
	 *   if($one->id == $many->cardId)
	 * }
	 */
	public function addRelation($entities, $entityType, $property) {

	}

	protected function execute($sql, array $params = [], $limit = null, $offset = null) {
		\OCP\Util::writeLog('deck', "DeckMapper SQL: " . $sql, \OCP\Util::DEBUG);
		return parent::execute($sql, $params, $limit, $offset);
	}

}