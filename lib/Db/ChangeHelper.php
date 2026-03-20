<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\ICache;
use OCP\ICacheFactory;
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
		?string $userId,
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

	public function cardChanged($cardId, $updateCard = true, ?int $boardId = null, ?int $stackId = null) {
		$time = time();
		$etag = md5($time . microtime());
		$this->cache->set(self::TYPE_CARD . '-' . $cardId, $etag);
		if ($updateCard) {
			$sql = 'UPDATE `*PREFIX*deck_cards` SET `last_modified` = ?, `last_editor` = ? WHERE `id` = ?';
			$this->db->executeUpdate($sql, [time(), $this->userId, $cardId]);
		}

		if ($boardId !== null && $stackId !== null) {
			$this->boardChanged($boardId);
			$this->stackChanged($stackId);
			return;
		}

		$sql = 'SELECT s.board_id as id, c.stack_id as stack_id FROM `*PREFIX*deck_stacks` as s inner join `*PREFIX*deck_cards` as c ON c.stack_id = s.id WHERE c.id = ?';
		$result = $this->db->executeQuery($sql, [$cardId]);
		if ($row = $result->fetch()) {
			$this->boardChanged($boardId ?? (int)$row['id']);
			$this->stackChanged($stackId ?? (int)$row['stack_id']);
		}
	}

	public function stackChanged($stackId, $updateBoard = true) {
		$time = time();
		$etag = md5($time . microtime());
		$this->cache->set(self::TYPE_CARD . '-' . $stackId, $etag);
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
		$entry = $this->cache->get($type . '-' . $id);
		if ($entry === 'null') {
			return '';
		}
		return $entry;
	}
}
