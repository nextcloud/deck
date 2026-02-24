<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Model\CardDetails;

class OverviewService {

	public function __construct(
		private readonly CardService $cardService,
		private readonly BoardMapper $boardMapper,
		private readonly CardMapper $cardMapper,
	) {
	}

	public function findUpcomingCards(string $userId): array {
		$userBoards = $this->boardMapper->findAllForUser($userId);

		$boardOwnerIds = array_filter(array_map(function (Board $board) {
			return count($board->getAcl() ?? []) === 0 ? $board->getId() : null;
		}, $userBoards));
		$boardSharedIds = array_filter(array_map(function (Board $board) {
			return count($board->getAcl() ?? []) > 0 ? $board->getId() : null;
		}, $userBoards));

		$foundCards = array_merge(
			// private board: get cards with due date
			$this->cardMapper->findAllWithDue($boardOwnerIds),
			// shared board: get all my assigned or unassigned cards
			$this->cardMapper->findToMeOrNotAssignedCards($boardSharedIds, $userId)
		);

		$this->cardService->enrichCards($foundCards);
		$overview = [];
		foreach ($foundCards as $card) {
			$diffDays = $card->getDaysUntilDue();

			$key = 'later';
			if ($diffDays === null) {
				$key = 'nodue';
			} elseif ($diffDays < 0) {
				$key = 'overdue';
			} elseif ($diffDays === 0) {
				$key = 'today';
			} elseif ($diffDays === 1) {
				$key = 'tomorrow';
			} elseif ($diffDays <= 7) {
				$key = 'nextSevenDays';
			}

			$card = (new CardDetails($card, $card->getRelatedBoard()));
			$overview[$key][] = $card->jsonSerialize();
		}
		return $overview;
	}
}
