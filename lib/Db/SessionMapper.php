<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, chandi Langecker (git@chandi.it)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
