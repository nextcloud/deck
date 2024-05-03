<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCA\Deck\Service\SessionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<Session> */
class SessionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'deck_sessions', Session::class);
	}

	public function find(int $boardId, string $userId, string $token): Session {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->andWhere($qb->expr()->gt('last_contact', $qb->createNamedParameter(time() - SessionService::SESSION_VALID_TIME)))
			->executeQuery();

		$data = $result->fetch();
		$result->closeCursor();
		if ($data === false) {
			throw new DoesNotExistException('Session is invalid');
		}
		$session = Session::fromRow($data);
		if ($session->getUserId() != $userId || $session->getBoardId() != $boardId) {
			throw new DoesNotExistException('Session is invalid');
		}
		return $session;
	}

	public function findAllActive($boardId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'board_id', 'last_contact', 'user_id', 'token')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId)))
			->andWhere($qb->expr()->gt('last_contact', $qb->createNamedParameter(time() - SessionService::SESSION_VALID_TIME)))
			->executeQuery();

		return $this->findEntities($qb);
	}
	public function deleteInactive(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lt('last_contact', $qb->createNamedParameter(time() - SessionService::SESSION_VALID_TIME)));
		return $qb->executeStatement();
	}
}
