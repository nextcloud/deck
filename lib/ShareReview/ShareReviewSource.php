<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\ShareReview;

use OCA\Deck\Db\Acl;
use OCA\ShareReview\Sources\ISource;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class ShareReviewSource implements ISource {

	private const ACL_TABLE = 'deck_board_acl';
	private const BOARDS_TABLE = 'deck_boards';
	private const PERMISSION_MANAGE = 32;

	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Deck';
	}

	/**
	 * @return list<array{id: int, app: string, object: string, initiator: string, type: int, recipient: string, permissions: int, password: bool, time: string, action: string}>
	 */
	public function getShares(): array {
		$rawShares = $this->fetchAllShares();
		$appName = $this->getName();
		$formatted = [];
		foreach ($rawShares as $share) {
			$formatted[] = [
				'id' => (int)$share['id'],
				'app' => $appName,
				'object' => $this->resolveObjectName($share),
				'initiator' => (string)$share['board_owner'],
				'type' => $this->mapParticipantType((int)$share['type']),
				'recipient' => (string)$share['participant'],
				'permissions' => $this->computePermissions($share),
				'password' => false,
				'time' => '1970-01-01 01:00:00',
				'action' => '',
			];
		}
		return $formatted;
	}

	public function deleteShare(string $shareId): bool {
		return false;
	}

	/** @return list<array<string, mixed>> */
	private function fetchAllShares(): array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select(
				'a.id', 'a.type', 'a.participant',
				'a.permission_edit', 'a.permission_share', 'a.permission_manage'
			)
				->addSelect($qb->createFunction('b.title AS board_title'))
				->addSelect($qb->createFunction('b.owner AS board_owner'))
				->from(self::ACL_TABLE, 'a')
				->leftJoin('a', self::BOARDS_TABLE, 'b', $qb->expr()->eq('a.board_id', 'b.id'))
				->orderBy('a.id', 'ASC');
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();
			return $rows;
		} catch (Exception $e) {
			$this->logger->error('Deck ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return [];
		}
	}

	/** @param array<string, mixed> $share */
	private function resolveObjectName(array $share): string {
		$title = (string)($share['board_title'] ?? '');
		$boardId = (int)$share['id'];
		return ($title !== '' ? $title : "Board $boardId") . ' (Board)';
	}

	private function mapParticipantType(int $type): int {
		return match($type) {
			Acl::PERMISSION_TYPE_USER => IShare::TYPE_USER,
			Acl::PERMISSION_TYPE_GROUP => IShare::TYPE_GROUP,
			Acl::PERMISSION_TYPE_REMOTE => IShare::TYPE_REMOTE,
			Acl::PERMISSION_TYPE_CIRCLE => IShare::TYPE_CIRCLE,
			default => IShare::TYPE_USER,
		};
	}

	/** @param array<string, mixed> $share */
	private function computePermissions(array $share): int {
		$permissions = Constants::PERMISSION_READ;
		if ($share['permission_edit']) {
			$permissions |= Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE | Constants::PERMISSION_DELETE;
		}
		if ($share['permission_share']) {
			$permissions |= Constants::PERMISSION_SHARE;
		}
		if ($share['permission_manage']) {
			$permissions |= self::PERMISSION_MANAGE;
		}
		return $permissions;
	}
}
