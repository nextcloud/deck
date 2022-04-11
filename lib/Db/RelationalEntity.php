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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;

class RelationalEntity extends Entity implements \JsonSerializable {
	private $_relations = [];
	private $_resolvedProperties = [];

	/**
	 * Mark a property as relation so it will not get updated using Mapper::update
	 * @param string $property string Name of the property
	 */
	public function addRelation($property) {
		if (!in_array($property, $this->_relations, true)) {
			$this->_relations[] = $property;
		}
	}

	/**
	 * Mark a property as resolvable via resolveRelation()
	 * @param string $property string Name of the property
	 */
	public function addResolvable($property) {
		$this->_resolvedProperties[$property] = null;
	}

	/**
	 * Mark am attribute as updated
	 * overwritten from \OCP\AppFramework\Db\Entity to avoid writing relational attributes
	 * @param string $attribute the name of the attribute
	 * @since 7.0.0
	 */
	protected function markFieldUpdated($attribute) {
		if (!in_array($attribute, $this->_relations, true)) {
			parent::markFieldUpdated($attribute);
		}
	}

	/**
	 * @return array serialized data
	 * @throws \ReflectionException
	 */
	public function jsonSerialize(): array {
		$properties = get_object_vars($this);
		$reflection = new \ReflectionClass($this);
		$json = [];
		foreach ($properties as $property => $value) {
			if (strpos($property, '_') !== 0 && $reflection->hasProperty($property)) {
				$propertyReflection = $reflection->getProperty($property);
				if (!$propertyReflection->isPrivate() && !in_array($property, $this->_resolvedProperties, true)) {
					$json[$property] = $this->getter($property);
				}
			}
		}
		foreach ($this->_resolvedProperties as $property => $value) {
			if ($value !== null) {
				$json[$property] = $value;
			}
		}
		if ($reflection->hasMethod('getETag')) {
			$json['ETag'] = $this->getETag();
		}
		return $json;
	}

	public function __toString(): string {
		return (string)$this->getId();
	}

	/*
	 * Resolve relational data from external methods
	 *
	 * example usage:
	 *
	 * in Board::__construct()
	 * 		$this->addResolvable('owner')
	 *
	 * in BoardMapper
	 * 		$board->resolveRelation('owner', function($owner) use (&$userManager) {
	 * 			return new \OCA\Deck\Db\User($userManager->get($owner));
	 * 		});
	 *
	 * resolved values can be obtained by calling resolveProperty
	 * e.g. $board->resolveOwner()
	 *
	 * @param string $property name of the property
	 * @param callable $resolver anonymous function to resolve relational
	 * data defined by $property as unique identifier
	 * @throws \Exception
	 */
	public function resolveRelation($property, $resolver) {
		$result = null;
		if ($property !== null && $this->$property !== null) {
			$result = $resolver($this->$property);
		}

		if ($result instanceof RelationalObject || $result === null) {
			$this->_resolvedProperties[$property] = $result;
		} else {
			throw new \Exception('resolver must return an instance of RelationalObject');
		}
	}

	public function __call($methodName, $args) {
		$attr = lcfirst(substr($methodName, 7));
		if (array_key_exists($attr, $this->_resolvedProperties) && strpos($methodName, 'resolve') === 0) {
			if ($this->_resolvedProperties[$attr] !== null) {
				return $this->_resolvedProperties[$attr];
			}
			return $this->getter($attr);
		}

		$attr = lcfirst(substr($methodName, 3));
		if (array_key_exists($attr, $this->_resolvedProperties) && strpos($methodName, 'set') === 0) {
			if (!is_scalar($args[0])) {
				$args[0] = $args[0]['primaryKey'];
			}
			parent::setter($attr, $args);
			return null;
		}
		return parent::__call($methodName, $args);
	}
}
