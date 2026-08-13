<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\BoardMapper;

class TeamBoardService {
	public function __construct(
		private BoardMapper $boardMapper,
		private CirclesService $circlesService,
	) {
	}

	/**
	 * When user is deleted all the boards attached to the team are transfered to another team member
	 *
	 * @return int[]
	 */
	public function transferTeamBoardsFromDeletedUser(string $userId): array {
		$transferredBoardIds = [];
		foreach ($this->boardMapper->findAllByOwner($userId) as $board) {
			$teamId = $board->getTeamId();
			if ($teamId === null || $teamId === '') {
				continue;
			}

			$nextOwner = $this->circlesService->findNextMemberUserId($teamId, $userId);
			if ($nextOwner === null) {
				continue;
			}

			$this->boardMapper->transferOwnership($userId, $nextOwner, $board->getId());
			$transferredBoardIds[] = $board->getId();
		}

		return $transferredBoardIds;
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
				continue;
			}

			$this->boardMapper->transferOwnership($userId, $nextOwner, $board->getId());
		}
	}

	/**
	 * When a team is deleted all boards attached to it are deleted too
	 */
	public function deleteBoardsAttachedToTeam(string $teamId): void {
		foreach ($this->boardMapper->findAllAttachedToTeam($teamId) as $board) {
			$this->boardMapper->delete($board);
		}
	}
}
