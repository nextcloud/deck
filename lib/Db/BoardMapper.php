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

use OCP\IDBConnection;


class BoardMapper extends DeckMapper implements IPermissionMapper {

	private $labelMapper;
	private $aclMapper;
	private $stackMapper;

	public function __construct(IDBConnection $db, LabelMapper $labelMapper, AclMapper $aclMapper, StackMapper $stackMapper) {
		parent::__construct($db, 'deck_boards', '\OCA\Deck\Db\Board');
		$this->labelMapper = $labelMapper;
		$this->aclMapper = $aclMapper;
		$this->stackMapper = $stackMapper;
	}


	/**
	 * @param $id
	 * @param bool $withLabels
	 * @param bool $withAcl
	 * @return \OCP\AppFramework\Db\Entity if not found
	 */
	public function find($id, $withLabels = false, $withAcl = false) {
		$sql = 'SELECT id, title, owner, color, archived FROM `*PREFIX*deck_boards` ' .
			'WHERE `id` = ?';
		$board = $this->findEntity($sql, [$id]);

		// Add labels
		if ($withLabels) {
			$labels = $this->labelMapper->findAll($id);
			$board->setLabels($labels);
		}

		// Add acl
		if ($withAcl) {
			$acl = $this->aclMapper->findAll($id);
			$board->setAcl($acl);
		}

		return $board;
	}

	/**
	 * Find all boards for a given user
	 *
	 * @param $userId
	 * @param null $limit
	 * @param null $offset
	 * @return array
	 */
	public function findAllByUser($userId, $limit = null, $offset = null) {
		$sql = 'SELECT id, title, owner, color, archived, 0 as shared FROM oc_deck_boards WHERE owner = ? UNION ' .
			'SELECT boards.id, title, owner, color, archived, 1 as shared FROM oc_deck_boards as boards ' .
			'JOIN oc_deck_board_acl as acl ON boards.id=acl.board_id WHERE acl.participant=? AND acl.type=\'user\' AND boards.owner != ?';
		$entries = $this->findEntities($sql, [$userId, $userId, $userId], $limit, $offset);
		/* @var Board $entry */
		foreach ($entries as $entry) {
			$acl = $this->aclMapper->findAll($entry->id);
			$entry->setAcl($acl);
		}
		return $entries;
	}

	/**
	 * Find all boards for a given user
	 *
	 * @param $userId
	 * @param $groups
	 * @param null $limit
	 * @param null $offset
	 * @return array
	 */
	public function findAllByGroups($userId, $groups, $limit = null, $offset = null) {
		if (count($groups) <= 0) {
			return [];
		}
		$sql = 'SELECT boards.id, title, owner, color, archived, 2 as shared FROM oc_deck_boards as boards ' .
			'INNER JOIN oc_deck_board_acl as acl ON boards.id=acl.board_id WHERE owner != ? AND type=\'group\' AND (';
		for ($i = 0; $i < count($groups); $i++) {
			$sql .= 'acl.participant = ? ';
			if (count($groups) > 1 && $i < count($groups) - 1) {
				$sql .= ' OR ';
			}
		}
		$sql .= ');';
		$entries = $this->findEntities($sql, array_merge([$userId], $groups), $limit, $offset);
		/* @var Board $entry */
		foreach ($entries as $entry) {
			$acl = $this->aclMapper->findAll($entry->id);
			$entry->setAcl($acl);
		}
		return $entries;
	}

	public function delete(/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
		\OCP\AppFramework\Db\Entity $entity) {
		// delete acl
		$acl = $this->aclMapper->findAll($entity->getId());
		foreach ($acl as $item) {
			$this->aclMapper->delete($item);
		}

		// delete stacks ( includes cards, assigned labels)
		$stacks = $this->stackMapper->findAll($entity->getId());
		foreach ($stacks as $stack) {
			$this->stackMapper->delete($stack);
		}
		// delete labels
		$labels = $this->labelMapper->findAll($entity->getId());
		foreach ($labels as $label) {
			$this->labelMapper->delete($label);
		}

		return parent::delete($entity);
	}

	public function isOwner($userId, $boardId) {
		$board = $this->find($boardId);
		return ($board->getOwner() === $userId);
	}

	public function findBoardId($id) {
		return $id;
	}


}