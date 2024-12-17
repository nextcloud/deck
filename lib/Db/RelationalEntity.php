<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	protected function markFieldUpdated(string $attribute): void {
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
			if (!str_starts_with($property, '_') && $reflection->hasProperty($property)) {
				$propertyReflection = $reflection->getProperty($property);
				if (!$propertyReflection->isPrivate() && !in_array($property, $this->_resolvedProperties, true)) {
					$json[$property] = $this->getter($property);
					if ($json[$property] instanceof \DateTimeInterface) {
						$json[$property] = $json[$property]->format('c');
					}
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

	public function __call(string $methodName, array $args) {
		$attr = lcfirst(substr($methodName, 7));
		if (array_key_exists($attr, $this->_resolvedProperties) && str_starts_with($methodName, 'resolve')) {
			if ($this->_resolvedProperties[$attr] !== null) {
				return $this->_resolvedProperties[$attr];
			}
			return $this->getter($attr);
		}

		$attr = lcfirst(substr($methodName, 3));
		if (array_key_exists($attr, $this->_resolvedProperties) && str_starts_with($methodName, 'set')) {
			if ($args[0] !== null && !is_scalar($args[0])) {
				$args[0] = $args[0]['primaryKey'];
			}
			parent::setter($attr, $args);
			return null;
		}
		return parent::__call($methodName, $args);
	}
}
