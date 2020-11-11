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
use OCP\Comments\ICommentsManager;
use OCP\IGroupManager;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCP\IUserManager;

class OverviewService {

	/** @var BoardMapper */
	private $boardMapper;
	/** @var LabelMapper */
	private $labelMapper;
	/** @var CardMapper */
	private $cardMapper;
	/** @var AssignmentMapper */
	private $assignedUsersMapper;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var ICommentsManager */
	private $commentsManager;
	/** @var AttachmentService */
	private $attachmentService;

	public function __construct(
		BoardMapper $boardMapper,
		LabelMapper $labelMapper,
		CardMapper $cardMapper,
		AssignmentMapper $assignedUsersMapper,
		IUserManager $userManager,
		IGroupManager $groupManager,
		ICommentsManager $commentsManager,
		AttachmentService $attachmentService
	) {
		$this->boardMapper = $boardMapper;
		$this->labelMapper = $labelMapper;
		$this->cardMapper = $cardMapper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
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
		$userBoards = $this->findAllBoardsFromUser($userId);
		$allDueCards = [];
		foreach ($userBoards as $userBoard) {
			$service = $this;
			$allDueCards[] = array_map(static function ($card) use ($service, $userBoard, $userId) {
				$service->enrich($card, $userId);
				$cardData = $card->jsonSerialize();
				$cardData['boardId'] = $userBoard->getId();
				return $cardData;
			}, $this->cardMapper->findAllWithDue($userBoard->getId()));
		}
		return $allDueCards;
	}

	public function findUpcomingCards(string $userId): array {
		$userBoards = $this->findAllBoardsFromUser($userId);
		$findCards = [];
		foreach ($userBoards as $userBoard) {
			$service = $this;

			if (count($userBoard->getAcl()) === 0) {
				// get cards with due date
				$findCards[] = array_map(static function ($card) use ($service, $userBoard, $userId) {
					$service->enrich($card, $userId);
					$cardData = $card->jsonSerialize();
					$cardData['boardId'] = $userBoard->getId();
					return $cardData;
				}, $this->cardMapper->findAllWithDue($userBoard->getId()));
			} else {
				// get assigned cards
				$findCards[] = array_map(static function ($card) use ($service, $userBoard, $userId) {
					$service->enrich($card, $userId);
					$cardData = $card->jsonSerialize();
					$cardData['boardId'] = $userBoard->getId();
					return $cardData;
				}, $this->cardMapper->findAssignedCards($userBoard->getId(), $userId));
			}
		}
		return $findCards;
	}

	// FIXME: This is duplicate code with the board service
	private function findAllBoardsFromUser(string $userId): array {
		$userInfo = $this->getBoardPrerequisites($userId);
		$userBoards = $this->boardMapper->findAllByUser($userInfo['user'], null, null);
		$groupBoards = $this->boardMapper->findAllByGroups($userInfo['user'], $userInfo['groups'],null, null);
		$circleBoards = $this->boardMapper->findAllByCircles($userInfo['user'], null, null);
		return array_unique(array_merge($userBoards, $groupBoards, $circleBoards));
	}

	private function getBoardPrerequisites($userId): array {
		$user = $this->userManager->get($userId);
		$groups = $user !== null ? $this->groupManager->getUserGroupIds($user) : [];
		return [
			'user' => $userId,
			'groups' => $groups
		];
	}
}
