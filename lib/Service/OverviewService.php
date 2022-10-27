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
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Model\CardDetails;
use OCP\Comments\ICommentsManager;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCP\IUserManager;

class OverviewService {
	private BoardMapper $boardMapper;
	private LabelMapper $labelMapper;
	private CardMapper $cardMapper;
	private AssignmentMapper $assignedUsersMapper;
	private IUserManager $userManager;
	private ICommentsManager $commentsManager;
	private AttachmentService $attachmentService;

	public function __construct(
		BoardMapper $boardMapper,
		LabelMapper $labelMapper,
		CardMapper $cardMapper,
		AssignmentMapper $assignedUsersMapper,
		IUserManager $userManager,
		ICommentsManager $commentsManager,
		AttachmentService $attachmentService
	) {
		$this->boardMapper = $boardMapper;
		$this->labelMapper = $labelMapper;
		$this->cardMapper = $cardMapper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->userManager = $userManager;
		$this->commentsManager = $commentsManager;
		$this->attachmentService = $attachmentService;
	}

	public function enrich(Card $card, string $userId): void {
		$cardId = $card->getId();

		$this->cardMapper->mapOwner($card);
		$card->setAssignedUsers($this->assignedUsersMapper->findAll($cardId));
		$card->setLabels($this->labelMapper->findAssignedLabelsForCard($cardId));
		$card->setAttachmentCount($this->attachmentService->count($cardId));

		$user = $this->userManager->get($userId);
		if ($user !== null) {
			$lastRead = $this->commentsManager->getReadMark('deckCard', (string)$card->getId(), $user);
			$count = $this->commentsManager->getNumberOfCommentsForObject('deckCard', (string)$card->getId(), $lastRead);
			$card->setCommentsUnread($count);
		}
	}

	public function findAllWithDue(string $userId): array {
		$userBoards = $this->boardMapper->findAllForUser($userId);
		$allDueCards = [];
		foreach ($userBoards as $userBoard) {
			$allDueCards[] = array_map(function ($card) use ($userBoard, $userId) {
				$this->enrich($card, $userId);
				return (new CardDetails($card, $userBoard))->jsonSerialize();
			}, $this->cardMapper->findAllWithDue($userBoard->getId()));
		}
		return array_merge(...$allDueCards);
	}

	public function findUpcomingCards(string $userId): array {
		$userBoards = $this->boardMapper->findAllForUser($userId);
		$foundCards = [];
		foreach ($userBoards as $userBoard) {
			if (count($userBoard->getAcl()) === 0) {
				// private board: get cards with due date
				$cards = $this->cardMapper->findAllWithDue($userBoard->getId());
			} else {
				// shared board: get all my assigned or unassigned cards
				$cards = $this->cardMapper->findToMeOrNotAssignedCards($userBoard->getId(), $userId);
			}

			$foundCards[] = array_map(
				function (Card $card) use ($userBoard, $userId) {
					$this->enrich($card, $userId);
					return (new CardDetails($card, $userBoard))->jsonSerialize();
				},
				$cards
			);
		}
		return array_merge(...$foundCards);
	}
}
