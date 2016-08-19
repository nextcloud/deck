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

/**
 * Created by PhpStorm.
 * User: jus
 * Date: 22.06.16
 * Time: 13:32
 */

namespace OCA\Deck\Db;


class Entity extends \OCP\AppFramework\Db\Entity {

	private $_relations = array();
	private $_updatedFields = array();

	/**
	 * Mark a property as relation so it will not get updated using Mapper::update
	 * @param string $property Name of the property
	 */
	public function addRelation(string $property) {
		if (!in_array($property, $this->_relations)) {
			$this->_relations[] = $property;
		}
	}
	/**
	 * Mark am attribute as updated
	 * overwritten from \OCP\AppFramework\Db\Entity to avoid writing relational attributes
	 * @param string $attribute the name of the attribute
	 * @since 7.0.0
	 */
	protected function markFieldUpdated($attribute){
		if(!in_array($attribute, $this->_relations)) {
			$this->_updatedFields[$attribute] = true;
		}
	}

	/**
	 * overwritten from \OCP\AppFramework\Db\Entity to avoid writing relational attributes
	 * @return array Array of field's update status
	 */
	public function getUpdatedFields(){
		return $this->_updatedFields;
	}



}