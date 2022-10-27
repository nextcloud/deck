<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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

use OCP\ICacheFactory;
use OCP\ICache;
use OCP\IDBConnection;
use OCP\IRequest;

class ChangeHelper {
	public const TYPE_BOARD = 'boardChanged';
	public const TYPE_CARD = 'cardChanged';

	private IDBConnection $db;
	private ICache $cache;
	private IRequest $request;
	private ?string $userId;

	public function __construct(
		IDBConnection $db,
		ICacheFactory $cacheFactory,
		IRequest $request,
		?string $userId
	) {
		$this->db = $db;
		$this->cache = $cacheFactory->createDistributed('deck_changes');
		$this->request = $request;
		$this->userId = $userId;
	}

	public function boardChanged($boardId) {
		$time = time();
		$etag = md5($time . microtime());
		$this->cache->set(self::TYPE_BOARD . '-' . $boardId, $etag);
		$sql = 'UPDATE `*PREFIX*deck_boards` SET `last_modified` = ? WHERE `id` = ?';
		$this->db->executeUpdate($sql, [$time, $boardId]);
	}

	public function cardChanged($cardId, $updateCard = true) {
		$time = time();
		$etag = md5($time . microtime());
		$this->cache->set(self::TYPE_CARD . '-' .$cardId, $etag);
		if ($updateCard) {
			$sql = 'UPDATE `*PREFIX*deck_cards` SET `last_modified` = ?, `last_editor` = ? WHERE `id` = ?';
			$this->db->executeUpdate($sql, [time(), $this->userId, $cardId]);
		}

		$sql = 'SELECT s.board_id as id, c.stack_id as stack_id FROM `*PREFIX*deck_stacks` as s inner join `*PREFIX*deck_cards` as c ON c.stack_id = s.id WHERE c.id = ?';
		$result = $this->db->executeQuery($sql, [$cardId]);
		if ($row = $result->fetch()) {
			$this->boardChanged($row['id']);
			$this->stackChanged($row['stack_id']);
		}
	}

	public function stackChanged($stackId, $updateBoard = true) {
		$time = time();
		$etag = md5($time . microtime());
		$this->cache->set(self::TYPE_CARD . '-' .$stackId, $etag);
		if ($updateBoard) {
			$sql = 'UPDATE `*PREFIX*deck_stacks` SET `last_modified` = ? WHERE `id` = ?';
			$this->db->executeUpdate($sql, [time(), $stackId]);
		}
	}

	public function checkEtag($type, $id) {
		$etag = $this->getEtag($type, $id);
		if ($this->request->getHeader('If-None-Match') === $etag) {
			return true;
		}
		return false;
	}

	public function getEtag($type, $id) {
		$entry = $this->cache->get($type . '-' .$id);
		if ($entry === 'null') {
			return '';
		}
		return $entry;
	}
}
