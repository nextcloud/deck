<?php
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Activity\ChangeSet;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\CardServiceValidator;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class CardService {
	private CardMapper $cardMapper;
	private StackMapper $stackMapper;
	private BoardMapper $boardMapper;
	private LabelMapper $labelMapper;
	private LabelService $labelService;
	private PermissionService $permissionService;
	private BoardService $boardService;
	private NotificationHelper $notificationHelper;
	private AssignmentMapper $assignedUsersMapper;
	private AttachmentService $attachmentService;
	private ?string $currentUser;
	private ActivityManager $activityManager;
	private ICommentsManager $commentsManager;
	private ChangeHelper $changeHelper;
	private IEventDispatcher $eventDispatcher;
	private IUserManager $userManager;
	private IURLGenerator $urlGenerator;
	private LoggerInterface $logger;
	private IRequest $request;
	private CardServiceValidator $cardServiceValidator;

	public function __construct(
		CardMapper $cardMapper,
		StackMapper $stackMapper,
		BoardMapper $boardMapper,
		LabelMapper $labelMapper,
		LabelService $labelService,
		PermissionService $permissionService,
		BoardService $boardService,
		NotificationHelper $notificationHelper,
		AssignmentMapper $assignedUsersMapper,
		AttachmentService $attachmentService,
		ActivityManager $activityManager,
		ICommentsManager $commentsManager,
		IUserManager $userManager,
		ChangeHelper $changeHelper,
		IEventDispatcher $eventDispatcher,
		IURLGenerator $urlGenerator,
		LoggerInterface $logger,
		IRequest $request,
		CardServiceValidator $cardServiceValidator,
		private IReferenceManager $referenceManager,
		?string $userId
	) {
		$this->cardMapper = $cardMapper;
		$this->stackMapper = $stackMapper;
		$this->boardMapper = $boardMapper;
		$this->labelMapper = $labelMapper;
		$this->labelService = $labelService;
		$this->permissionService = $permissionService;
		$this->boardService = $boardService;
		$this->notificationHelper = $notificationHelper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->attachmentService = $attachmentService;
		$this->activityManager = $activityManager;
		$this->commentsManager = $commentsManager;
		$this->userManager = $userManager;
		$this->changeHelper = $changeHelper;
		$this->eventDispatcher = $eventDispatcher;
		$this->currentUser = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->request = $request;
		$this->cardServiceValidator = $cardServiceValidator;
	}

	public function enrichCard($card) {
		return $this->enrichCards([$card])[0];
	}

	public function enrichCards($cards) {
		$user = $this->userManager->get($this->currentUser);

		$cardIds = array_map(function (Card $card) use ($user) {
			// Everything done in here might be heavy as it is executed for every card
			$cardId = $card->getId();
			$this->cardMapper->mapOwner($card);

			$card->setAttachmentCount($this->attachmentService->count($cardId));

			// TODO We should find a better way just to get the comment count so we can save 1-3 queries per card here
			$countComments = $this->commentsManager->getNumberOfCommentsForObject('deckCard', (string)$card->getId());
			$lastRead = $countComments > 0 ? $this->commentsManager->getReadMark('deckCard', (string)$card->getId(), $user) : null;
			$countUnreadComments = $lastRead ? $this->commentsManager->getNumberOfCommentsForObject('deckCard', (string)$card->getId(), $lastRead) : 0;
			$card->setCommentsUnread($countUnreadComments);
			$card->setCommentsCount($countComments);

			$stack = $this->stackMapper->find($card->getStackId());
			$board = $this->boardService->find($stack->getBoardId(), false);
			$card->setRelatedStack($stack);
			$card->setRelatedBoard($board);

			return $card->getId();
		}, $cards);

		$assignedLabels = $this->labelMapper->findAssignedLabelsForCards($cardIds);
		$assignedUsers = $this->assignedUsersMapper->findIn($cardIds);

		foreach ($cards as $card) {
			$cardLabels = array_values(array_filter($assignedLabels, function (Label $label) use ($card) {
				return $label->getCardId() === $card->getId();
			}));
			$cardAssignedUsers = array_values(array_filter($assignedUsers, function (Assignment $assignment) use ($card) {
				return $assignment->getCardId() === $card->getId();
			}));
			$card->setLabels($cardLabels);
			$card->setAssignedUsers($cardAssignedUsers);
		}

		return array_map(
			function (Card $card): CardDetails {
				$referenceData = $this->referenceManager->resolveReference($card->getTitle());
				$cardDetails = new CardDetails($card);
				if ($referenceData) {
					$cardDetails->setReferenceData($referenceData);
				}
				return $cardDetails;
			},
			$cards
		);
	}

	private function applyReferenceData(CardDetails $cardDetails) {


	}
	public function fetchDeleted($boardId) {
		$this->cardServiceValidator->check(compact('boardId'));
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		$cards = $this->cardMapper->findDeleted($boardId);
		$this->enrichCards($cards);
		return $cards;
	}

	/**
	 * @return \OCA\Deck\Db\RelationalEntity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function find(int $cardId) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		$card = $this->cardMapper->find($cardId);
		[$card] = $this->enrichCards([$card]);

		// Attachments are only enriched on individual card fetching
		$attachments = $this->attachmentService->findAll($cardId, true);
		if ($this->request->getParam('apiVersion') === '1.0') {
			$attachments = array_filter($attachments, function ($attachment) {
				return $attachment->getType() === 'deck_file';
			});
		}
		$card->setAttachments($attachments);

		return $card;
	}

	public function findCalendarEntries($boardId) {
		try {
			$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		} catch (NoPermissionException $e) {
			$this->logger->error('Unable to check permission for a previously obtained board ' . $boardId, ['exception' => $e]);
			return [];
		}
		$cards = $this->cardMapper->findCalendarEntries($boardId);
		$this->enrichCards($cards);
		return $cards;
	}

	/**
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param integer $order
	 * @param $description
	 * @param $owner
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadrequestException
	 */
	public function create($title, $stackId, $type, $order, $owner, $description = '', $duedate = null) {
		$this->cardServiceValidator->check(compact('title', 'stackId', 'type', 'order', 'owner'));

		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->stackMapper, $stackId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = new Card();
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType($type);
		$card->setOrder($order);
		$card->setOwner($owner);
		$card->setDescription($description);
		$card->setDuedate($duedate);
		$card = $this->cardMapper->insert($card);

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_CREATE);
		$this->changeHelper->cardChanged($card->getId(), false);
		$this->eventDispatcher->dispatchTyped(new CardCreatedEvent($card));

		return $this->enrichCard($card);
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete($id) {
		if (is_numeric($id) === false) {
			throw new BadRequestException('card id must be a number');
		}

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setDeletedAt(time());
		$this->cardMapper->update($card);

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_DELETE);
		$this->notificationHelper->markDuedateAsRead($card);
		$this->changeHelper->cardChanged($card->getId(), false);
		$this->eventDispatcher->dispatchTyped(new CardDeletedEvent($card));

		return $card;
	}

	/**
	 * @param $id
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param $owner
	 * @param $description
	 * @param $order
	 * @param $duedate
	 * @param $deletedAt
	 * @param $archived
	 * @param $done
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update($id, $title, $stackId, $type, $owner, $description = '', $order = 0, $duedate = null, $deletedAt = null, $archived = null, ?OptionalNullableValue $done = null) {
		$this->cardServiceValidator->check(compact('id', 'title', 'stackId', 'type', 'owner', 'order'));

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT, allowDeletedCard: true);
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);

		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		if ($archived !== null && $card->getArchived() && $archived === true) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}

		if ($card->getDeletedAt() !== 0) {
			if ($deletedAt === null || $deletedAt > 0) {
				// Only allow operations when restoring the card
				throw new NoPermissionException('Operation not allowed. This card was deleted.');
			}
		}

		$changes = new ChangeSet($card);
		if ($card->getLastEditor() !== $this->currentUser && $card->getLastEditor() !== null) {
			$this->activityManager->triggerEvent(
				ActivityManager::DECK_OBJECT_CARD,
				$card,
				ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION,
				[
					'before' => $card->getDescriptionPrev(),
					'after' => $card->getDescription()
				],
				$card->getLastEditor()
			);

			$card->setDescriptionPrev($card->getDescription());
			$card->setLastEditor($this->currentUser);
		}
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType($type);
		$card->setOrder($order);
		$card->setOwner($owner);
		$card->setDuedate($duedate ? new \DateTime($duedate) : null);
		$resetDuedateNotification = false;
		if (
			$card->getDuedate() === null ||
			($card->getDuedate()) != ($changes->getBefore()->getDuedate())
		) {
			$card->setNotified(false);
			$resetDuedateNotification = true;
		}

		if ($deletedAt !== null) {
			$card->setDeletedAt($deletedAt);
		}
		if ($archived !== null) {
			$card->setArchived($archived);
		}
		if ($done !== null) {
			$card->setDone($done->getValue());
		} else {
			$card->setDone(null);
		}


		// Trigger update events before setting description as it is handled separately
		$changes->setAfter($card);
		$this->activityManager->triggerUpdateEvents(ActivityManager::DECK_OBJECT_CARD, $changes, ActivityManager::SUBJECT_CARD_UPDATE);

		if ($card->getDescriptionPrev() === null) {
			$card->setDescriptionPrev($card->getDescription());
		}
		$card->setDescription($description);

		// @var Card $card
		$card = $this->cardMapper->update($card);
		$oldBoardId = $this->stackMapper->findBoardId($changes->getBefore()->getStackId());
		$boardId = $this->cardMapper->findBoardId($card->getId());
		if($boardId !== $oldBoardId) {
			$stack = $this->stackMapper->find($card->getStackId());
			$board = $this->boardService->find($this->cardMapper->findBoardId($card->getId()));
			$boardLabels = $board->getLabels() ?? [];
			foreach($card->getLabels() as $cardLabel) {
				$this->removeLabel($card->getId(), $cardLabel->getId());
				$label = $this->labelMapper->find($cardLabel->getId());
				$filteredLabels = array_values(array_filter($boardLabels, fn ($item) => $item->getTitle() === $label->getTitle()));
				// clone labels that are assigned to card but don't exist in new board
				if (empty($filteredLabels)) {
					if ($this->permissionService->getPermissions($boardId)[Acl::PERMISSION_MANAGE] === true) {
						$newLabel = $this->labelService->create($label->getTitle(), $label->getColor(), $board->getId());
						$boardLabels[] = $label;
						$this->assignLabel($card->getId(), $newLabel->getId());
					}
				} else {
					$this->assignLabel($card->getId(), $filteredLabels[0]->getId());
				}
			}
			$board->setLabels($boardLabels);
			$this->boardMapper->update($board);
			$this->changeHelper->boardChanged($board->getId());
		}

		if ($resetDuedateNotification) {
			$this->notificationHelper->markDuedateAsRead($card);
		}
		$this->changeHelper->cardChanged($card->getId(), true);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		return $card;
	}

	/**
	 * @param $id
	 * @param $title
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function rename($id, $title) {
		$this->cardServiceValidator->check(compact('id', 'title'));

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$card->setTitle($title);
		$this->changeHelper->cardChanged($card->getId(), false);
		$update = $this->cardMapper->update($card);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $update;
	}

	/**
	 * @param $id
	 * @param $stackId
	 * @param $order
	 * @return array
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function reorder($id, $stackId, $order) {
		$this->cardServiceValidator->check(compact('id', 'stackId', 'order'));


		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);

		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}

		$card = $this->cardMapper->find($id);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$changes = new ChangeSet($card);
		$card->setStackId($stackId);
		$this->cardMapper->update($card);
		$changes->setAfter($card);
		$this->activityManager->triggerUpdateEvents(ActivityManager::DECK_OBJECT_CARD, $changes, ActivityManager::SUBJECT_CARD_UPDATE);

		$cards = $this->cardMapper->findAll($stackId);
		$result = [];
		$i = 0;
		foreach ($cards as $card) {
			if ($card->getArchived()) {
				throw new StatusException('Operation not allowed. This card is archived.');
			}
			if ($card->id === $id) {
				$card->setOrder($order);
				$card->setLastModified(time());
			}

			if ($i === $order) {
				$i++;
			}

			if ($card->id !== $id) {
				$card->setOrder($i++);
			}
			$this->cardMapper->update($card);
			$result[$card->getOrder()] = $card;
		}
		$this->changeHelper->cardChanged($id, false);
		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return array_values($result);
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function archive($id) {
		$this->cardServiceValidator->check(compact('id'));


		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setArchived(true);
		$newCard = $this->cardMapper->update($card);
		$this->notificationHelper->markDuedateAsRead($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_ARCHIVE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $newCard;
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function unarchive($id) {
		$this->cardServiceValidator->check(compact('id'));


		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setArchived(false);
		$newCard = $this->cardMapper->update($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_UNARCHIVE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $newCard;
	}

	/**
	 * @param $id
	 * @return \OCA\Deck\Db\Card
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function done(int $id): Card {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setDone(new \DateTime());
		$newCard = $this->cardMapper->update($card);
		$this->notificationHelper->markDuedateAsRead($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_DONE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $newCard;
	}

	/**
	 * @param $id
	 * @return \OCA\Deck\Db\Card
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function undone(int $id): Card {
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setDone(null);
		$newCard = $this->cardMapper->update($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_UNDONE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $newCard;
	}

	/**
	 * @param $cardId
	 * @param $labelId
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function assignLabel($cardId, $labelId) {
		$this->cardServiceValidator->check(compact('cardId', 'labelId'));


		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$label = $this->labelMapper->find($labelId);
		$this->cardMapper->assignLabel($cardId, $labelId);
		$this->changeHelper->cardChanged($cardId);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_LABEL_ASSIGN, ['label' => $label]);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));
	}

	/**
	 * @param $cardId
	 * @param $labelId
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function removeLabel($cardId, $labelId) {
		$this->cardServiceValidator->check(compact('cardId', 'labelId'));


		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$label = $this->labelMapper->find($labelId);
		$this->cardMapper->removeLabel($cardId, $labelId);
		$this->changeHelper->cardChanged($cardId);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_LABEL_UNASSING, ['label' => $label]);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));
	}

	public function getCardUrl($cardId) {
		$boardId = $this->cardMapper->findBoardId($cardId);

		return $this->urlGenerator->linkToRouteAbsolute('deck.page.index') . "#/board/$boardId/card/$cardId";
	}

	public function getRedirectUrlForCard($cardId) {
		return $this->urlGenerator->linkToRouteAbsolute('deck.page.index') . "card/$cardId";
	}
}
