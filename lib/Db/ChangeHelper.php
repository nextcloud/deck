<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCA\Deck\Exceptions\PreconditionFailedException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IRequest;

class ChangeHelper {
	public const TYPE_BOARD = 'boardChanged';
	public const TYPE_CARD = 'cardChanged';
	public const TYPE_STACK = 'stackChanged';
	public const TYPE_LABEL = 'labelChanged';

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

	public function cardChanged($cardId, $updateCard = true) {
		$time = time();
		$etag = md5($time . microtime());
		$this->cache->set(self::TYPE_CARD . '-' . $cardId, $etag);
		if ($updateCard) {
			$sql = 'UPDATE `*PREFIX*deck_cards` SET `last_modified` = ?, `last_editor` = ? WHERE `id` = ?';
			$this->db->executeUpdate($sql, [time(), $this->userId, $cardId]);
		}

		$sql = 'SELECT s.board_id as id, c.stack_id as stack_id FROM `*PREFIX*deck_stacks` as s inner join `*PREFIX*deck_cards` as c ON c.stack_id = s.id WHERE c.id = ?';
		$result = $this->db->executeQuery($sql, [$cardId]);
		if ($row = $result->fetch()) {
			$this->boardChanged($row['id']);
			$this->stackChanged($row['stack_id'], true, false);
		}
	}

	public function stackChanged($stackId, $updateStack = true, $updateBoard = true) {
		$time = time();
		$etag = md5($time . microtime());
		$this->cache->set(self::TYPE_STACK . '-' . $stackId, $etag);
		if ($updateStack) {
			$sql = 'UPDATE `*PREFIX*deck_stacks` SET `last_modified` = ? WHERE `id` = ?';
			$this->db->executeUpdate($sql, [$time, $stackId]);
		}

		if ($updateBoard) {
			$sql = 'SELECT board_id FROM `*PREFIX*deck_stacks` WHERE id = ?';
			$result = $this->db->executeQuery($sql, [$stackId]);
			if ($row = $result->fetch()) {
				$this->boardChanged($row['board_id']);
			}
		}
	}

	public function labelChanged($labelId, $updateLabel = true) {
		$time = time();
		$etag = md5($time . microtime());
		$this->cache->set(self::TYPE_LABEL . '-' . $labelId, $etag);
		if ($updateLabel) {
			$sql = 'UPDATE `*PREFIX*deck_labels` SET `last_modified` = ? WHERE `id` = ?';
			$this->db->executeUpdate($sql, [$time, $labelId]);
		}

		$sql = 'SELECT board_id FROM `*PREFIX*deck_labels` WHERE id = ?';
		$result = $this->db->executeQuery($sql, [$labelId]);
		if ($row = $result->fetch()) {
			$this->boardChanged($row['board_id']);
		}
	}

	public function checkIfMatch($type, $id, $fallbackEtag = null) {
		$ifMatch = $this->request->getHeader('If-Match');
		if ($ifMatch === '') {
			return;
		}
		$etag = $this->getEtag($type, $id);
		if ($etag === '' && $fallbackEtag !== null) {
			$etag = $fallbackEtag;
		}
		if ($ifMatch !== '*' && $ifMatch !== $etag && $ifMatch !== '"' . $etag . '"') {
			throw new PreconditionFailedException('If-Match header does not match the current ETag');
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
