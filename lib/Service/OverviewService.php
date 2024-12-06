<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Model\CardDetails;
use OCP\Comments\ICommentsManager;
use OCP\IUserManager;

class OverviewService {
	private CardService $cardService;
	private BoardMapper $boardMapper;
	private LabelMapper $labelMapper;
	private CardMapper $cardMapper;
	private AssignmentMapper $assignedUsersMapper;
	private IUserManager $userManager;
	private ICommentsManager $commentsManager;
	private AttachmentService $attachmentService;

	public function __construct(
		CardService $cardService,
		BoardMapper $boardMapper,
		LabelMapper $labelMapper,
		CardMapper $cardMapper,
		AssignmentMapper $assignedUsersMapper,
		IUserManager $userManager,
		ICommentsManager $commentsManager,
		AttachmentService $attachmentService,
	) {
		$this->cardService = $cardService;
		$this->boardMapper = $boardMapper;
		$this->labelMapper = $labelMapper;
		$this->cardMapper = $cardMapper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->userManager = $userManager;
		$this->commentsManager = $commentsManager;
		$this->attachmentService = $attachmentService;
	}

	public function findUpcomingCards(string $userId): array {
		$userBoards = $this->boardMapper->findAllForUser($userId);

		$boardOwnerIds = array_filter(array_map(function (Board $board) {
			return count($board->getAcl()) === 0 ? $board->getId() : null;
		}, $userBoards));
		$boardSharedIds = array_filter(array_map(function (Board $board) {
			return count($board->getAcl()) > 0 ? $board->getId() : null;
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
