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

/**
 * Class DeckMapper
 *
 * @package OCA\Deck\Db
 * @deprecated use QBMapper
 *
 * TODO: Move to QBMapper once Nextcloud 14 is a minimum requirement
 */
class DeckMapper extends Mapper {

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function find($id) {
		$sql = 'SELECT * FROM `' . $this->tableName . '` ' . 'WHERE `id` = ?';
		return $this->findEntity($sql, [$id]);
	}

	protected function execute($sql, array $params = [], $limit = null, $offset = null) {
		return parent::execute($sql, $params, $limit, $offset);
	}
}
