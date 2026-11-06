<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AuditLog\Operation;
use OCA\Deck\Db\BoardMapper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;

class TeamBoardService {
	public function __construct(
		private BoardMapper $boardMapper,
		private CirclesService $circlesService,
		private IEventDispatcher $eventDispatcher,
	) {
	}

	/**
	 * When a user leaves the team,transfer ownership, or delete board
	 */
	public function handleMemberLeftTeam(string $teamId, string $userId): void {
		foreach ($this->boardMapper->findAllAttachedToTeam($teamId) as $board) {
			if ($board->getOwner() !== $userId) {
				continue;
			}

			$nextOwner = $this->circlesService->findNextMemberUserId($teamId, $userId);
			if ($nextOwner === null) {
				$this->boardMapper->delete($board);
				$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
					'Board %s was permanently deleted because its owner "%s" left team "%s"',
					[
						'boardId' => $board->getId(),
						'owner' => $userId,
						'teamId' => $teamId,
					],
					false,
					Operation::BoardPurged->value,
				));
				continue;
			}

			$this->boardMapper->transferOwnership($userId, $nextOwner, $board->getId());
			$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
				'Ownership of board %s was transferred from "%s" to "%s" after leaving team "%s"',
				[
					'boardId' => $board->getId(),
					'previousOwner' => $userId,
					'newOwner' => $nextOwner,
					'teamId' => $teamId,
				],
				false,
				Operation::BoardOwnershipTransferred->value,
			));
		}
	}

	/**
	 * When a team is deleted all boards attached to it are deleted too
	 */
	public function deleteBoardsAttachedToTeam(string $teamId): void {
		foreach ($this->boardMapper->findAllAttachedToTeam($teamId) as $board) {
			$this->boardMapper->delete($board);
			$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
				'Board %s was permanently deleted together with team "%s"',
				[
					'boardId' => $board->getId(),
					'teamId' => $teamId,
				],
				false,
				Operation::BoardPurged->value,
			));
		}
	}
}
