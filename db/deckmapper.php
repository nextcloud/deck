<?php

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\Mapper;

abstract class DeckMapper extends Mapper {

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
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

}