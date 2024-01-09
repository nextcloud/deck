<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Maxence Lange <maxence@artificial-owl.com>
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
		AttachmentService $attachmentService
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
