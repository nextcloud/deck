<?php
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

use OCA\Deck\Activity\ChangeSet;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\IPermissionMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCP\IUserManager;
use OCA\Deck\BadRequestException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class DashboardService {
	private $boardMapper;
	private $labelMapper;
	private $cardMapper;
	private $assignedUsersMapper;
	private $userManager;
	private $groupManager;
	private $userId;
	/** @var EventDispatcherInterface */
	private $eventDispatcher;
	
	public function __construct(
		BoardMapper $boardMapper,
		LabelMapper $labelMapper,
		CardMapper $cardMapper,
		AssignedUsersMapper $assignedUsersMapper,
		IUserManager $userManager,
		IGroupManager $groupManager,
		EventDispatcherInterface $eventDispatcher,
		$userId
	) {
		$this->boardMapper = $boardMapper;
		$this->labelMapper = $labelMapper;
		$this->cardMapper = $cardMapper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->eventDispatcher = $eventDispatcher;
		$this->userId = $userId;
	}

	public function enrich($card) {
		$cardId = $card->getId();
		$this->cardMapper->mapOwner($card);
		$card->setAssignedUsers($this->assignedUsersMapper->find($cardId));
		$card->setLabels($this->labelMapper->findAssignedLabelsForCard($cardId));
		//$card->setAttachmentCount($this->attachmentService->count($cardId));
		//$user = $this->userManager->get($this->userId);
		//$lastRead = $this->commentsManager->getReadMark('deckCard', (string)$card->getId(), $user);
		//$count = $this->commentsManager->getNumberOfCommentsForObject('deckCard', (string)$card->getId(), $lastRead);
		//$card->setCommentsUnread($count);
	}

	/**
	 * Set a different user than the current one, e.g. when no user is available in occ
	 *
	 * @param string $userId
	 */
	public function setUserId(string $userId): void {
		$this->userId = $userId;
	}

	
	/**
	 * @return array
	 */
	public function findAllWithDue($userId) {
		$userInfo = $this->getBoardPrerequisites();
		$userBoards = $this->findAllBoardsFromUser($userInfo);
		$allDueCards = [];
		foreach ($userBoards as $userBoard) {
			$service = $this;
			$allDueCards[] = array_map(function ($card) use ($service, $userBoard) {
				$service->enrich($card);
				$cardData = $card->jsonSerialize();
				$cardData['boardId'] = $userBoard->getId();
				return $cardData;
			}, $this->cardMapper->findAllWithDue($userBoard->getId()));
		}
		return $allDueCards;
	}

	/**
	 * @return array
	 */
	public function findAssignedCards($userId) {
		$userInfo = $this->getBoardPrerequisites();
		$userBoards = $this->findAllBoardsFromUser($userInfo);
		$allAssignedCards = [];
		foreach ($userBoards as $userBoard) {
			$service = $this;
			$allAssignedCards[] = array_map(function ($card) use ($service, $userBoard) {
				$service->enrich($card);
				$cardData = $card->jsonSerialize();
				$cardData['boardId'] = $userBoard->getId();
				return $cardData;
			}, $this->cardMapper->findAssignedCards($userBoard->getId(), $this->userId));
		}
		return $allAssignedCards;
	}

	/**
	 * @return array
	 */
	private function findAllBoardsFromUser($userInfo, $since = -1) {
		$userBoards = $this->boardMapper->findAllByUser($userInfo['user'], null, null, $since);
		$groupBoards = $this->boardMapper->findAllByGroups($userInfo['user'], $userInfo['groups'],null, null,  $since);
		$circleBoards = $this->boardMapper->findAllByCircles($userInfo['user'], null, null,  $since);
		return array_merge($userBoards, $groupBoards, $circleBoards);
	}

	/**
	 * @return array
	 */
	private function getBoardPrerequisites() {
		$groups = $this->groupManager->getUserGroupIds(
			$this->userManager->get($this->userId)
		);
		return [
			'user' => $this->userId,
			'groups' => $groups
		];
	}


}
