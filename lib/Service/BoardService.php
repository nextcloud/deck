<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Activity\ChangeSet;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\IPermissionMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Session;
use OCA\Deck\Db\SessionMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\Event\AclUpdatedEvent;
use OCA\Deck\Event\BoardUpdatedEvent;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\Validators\BoardServiceValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as DbException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class BoardService {
	private ?array $boardsCacheFull = null;
	private ?array $boardsCachePartial = null;

	public function __construct(
		private BoardMapper $boardMapper,
		private StackMapper $stackMapper,
		private CardMapper $cardMapper,
		private IConfig $config,
		private IL10N $l10n,
		private LabelMapper $labelMapper,
		private AclMapper $aclMapper,
		private PermissionService $permissionService,
		private AssignmentService $assignmentService,
		private NotificationHelper $notificationHelper,
		private AssignmentMapper $assignedUsersMapper,
		private ActivityManager $activityManager,
		private IEventDispatcher $eventDispatcher,
		private ChangeHelper $changeHelper,
		private IURLGenerator $urlGenerator,
		private IDBConnection $connection,
		private BoardServiceValidator $boardServiceValidator,
		private SessionMapper $sessionMapper,
		private ?string $userId,
	) {
	}

	/**
	 * Set a different user than the current one, e.g. when no user is available in occ
	 *
	 * @param string $userId
	 */
	public function setUserId(string $userId): void {
		$this->userId = $userId;
		$this->permissionService->setUserId($userId);
	}

	/**
	 * Get all boards that are shared with a user, their groups or circles
	 */
	public function getUserBoards(?int $since = null, bool $includeArchived = true, ?int $before = null,
		?string $term = null): array {
		return $this->boardMapper->findAllForUser($this->userId, $since, $includeArchived, $before, $term);
	}

	/**
	 * @return Board[]
	 */
	public function findAll(int $since = -1, bool $fullDetails = false, bool $includeArchived = true): array {
		$complete = $this->getUserBoards($since, $includeArchived);
		return $this->enrichBoards($complete, $fullDetails);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function find(int $boardId, bool $fullDetails = true, bool $allowDeleted = false): Board {
		$this->boardServiceValidator->check(compact('boardId'));

		if (isset($this->boardsCacheFull[$boardId]) && $fullDetails) {
			return $this->boardsCacheFull[$boardId];
		}

		if (isset($this->boardsCachePartial[$boardId]) && !$fullDetails) {
			return $this->boardsCachePartial[$boardId];
		}

		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		/** @var Board $board */
		$board = $this->boardMapper->find($boardId, true, true, $allowDeleted);
		[$board] = $this->enrichBoards([$board], $fullDetails);
		return $board;
	}

	/**
	 * @param $mapper
	 * @param $id
	 * @return bool
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function isArchived($mapper, $id) {
		$this->boardServiceValidator->check(compact('id'));

		try {
			$boardId = $id;
			if ($mapper instanceof IPermissionMapper) {
				$boardId = $mapper->findBoardId($id);
			}
			if ($boardId === null) {
				return false;
			}
		} catch (DoesNotExistException $exception) {
			return false;
		}
		$board = $this->find($boardId);
		return $board->getArchived();
	}

	/**
	 * @param $mapper
	 * @param $id
	 * @return bool
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function isDeleted($mapper, $id) {
		$this->boardServiceValidator->check(compact('mapper', 'id'));

		try {
			$boardId = $id;
			if ($mapper instanceof IPermissionMapper) {
				$boardId = $mapper->findBoardId($id);
			}
			if ($boardId === null) {
				return false;
			}
		} catch (DoesNotExistException $exception) {
			return false;
		}
		$board = $this->find($boardId);
		return $board->getDeletedAt() > 0;
	}


	/**
	 * @param $title
	 * @param $userId
	 * @param $color
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws BadRequestException
	 */
	public function create($title, $userId, $color) {
		$this->boardServiceValidator->check(compact('title', 'userId', 'color'));

		if (!$this->permissionService->canCreate()) {
			throw new NoPermissionException('Creating boards has been disabled for your account.');
		}

		$board = new Board();
		$board->setTitle($title);
		$board->setOwner($userId);
		$board->setColor($color);
		$new_board = $this->boardMapper->insert($board);

		// create new labels
		$default_labels = [
			'31CC7C' => $this->l10n->t('Finished'),
			'317CCC' => $this->l10n->t('To review'),
			'FF7A66' => $this->l10n->t('Action needed'),
			'F1DB50' => $this->l10n->t('Later')
		];
		$labels = [];
		foreach ($default_labels as $labelColor => $labelTitle) {
			$label = new Label();
			$label->setColor($labelColor);
			$label->setTitle($labelTitle);
			$label->setBoardId($new_board->getId());
			$labels[] = $this->labelMapper->insert($label);
		}
		$new_board->setLabels($labels);
		$this->boardMapper->mapOwner($new_board);
		$permissions = $this->permissionService->matchPermissions($new_board);
		$new_board->setPermissions([
			'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ] ?? false,
			'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT] ?? false,
			'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE] ?? false,
			'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE] ?? false
		]);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_BOARD, $new_board, ActivityManager::SUBJECT_BOARD_CREATE, [], $userId);
		$this->changeHelper->boardChanged($new_board->getId());

		return $new_board;
	}

	/**
	 * @param $id
	 * @return Board
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete($id) {
		$this->boardServiceValidator->check(compact('id'));

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_MANAGE);
		$board = $this->find($id);
		if ($board->getDeletedAt() > 0) {
			throw new BadRequestException('This board has already been deleted');
		}
		$board->setDeletedAt(time());
		$board = $this->boardMapper->update($board);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_BOARD, $board, ActivityManager::SUBJECT_BOARD_DELETE);
		$this->changeHelper->boardChanged($board->getId());

		return $board;
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function deleteUndo($id) {
		$this->boardServiceValidator->check(compact('id'));

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_MANAGE);
		$board = $this->find($id, allowDeleted: true);
		$board->setDeletedAt(0);
		$board = $this->boardMapper->update($board);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_BOARD, $board, ActivityManager::SUBJECT_BOARD_RESTORE);
		$this->changeHelper->boardChanged($board->getId());

		return $board;
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function deleteForce($id) {
		$this->boardServiceValidator->check(compact('id'));

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_MANAGE);
		$board = $this->find($id, allowDeleted: true);
		$delete = $this->boardMapper->delete($board);

		return $delete;
	}

	/**
	 * @param $id
	 * @param $title
	 * @param $color
	 * @param $archived
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update($id, $title, $color, $archived) {
		$this->boardServiceValidator->check(compact('id', 'title', 'color', 'archived'));

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_MANAGE);
		$board = $this->find($id);
		$changes = new ChangeSet($board);
		$board->setTitle($title);
		$board->setColor($color);
		$board->setArchived($archived);
		$changes->setAfter($board);
		$this->boardMapper->update($board); // operate on clone so we can check for updated fields
		$this->boardMapper->mapOwner($board);
		$this->activityManager->triggerUpdateEvents(ActivityManager::DECK_OBJECT_BOARD, $changes, ActivityManager::SUBJECT_BOARD_UPDATE);
		$this->changeHelper->boardChanged($board->getId());
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($board->getId()));

		return $board;
	}

	private function applyPermissions($boardId, $edit, $share, $manage, $oldAcl = null) {
		try {
			$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_MANAGE);
		} catch (NoPermissionException $e) {
			$acls = $this->aclMapper->findAll($boardId);
			$edit = $this->permissionService->userCan($acls, Acl::PERMISSION_EDIT, $this->userId) ? $edit : $oldAcl?->getPermissionEdit() ?? false;
			$share = $this->permissionService->userCan($acls, Acl::PERMISSION_SHARE, $this->userId) ? $share : $oldAcl?->getPermissionShare() ?? false;
			$manage = $this->permissionService->userCan($acls, Acl::PERMISSION_MANAGE, $this->userId) ? $manage : $oldAcl?->getPermissionManage() ?? false;
		}
		return [$edit, $share, $manage];
	}

	public function enrichWithBoardSettings(Board $board) {
		$globalCalendarConfig = (bool)$this->config->getUserValue($this->userId, Application::APP_ID, 'calendar', true);
		$settings = [
			'notify-due' => $this->config->getUserValue($this->userId, Application::APP_ID, 'board:' . $board->getId() . ':notify-due', ConfigService::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED),
			'calendar' => $this->config->getUserValue($this->userId, Application::APP_ID, 'board:' . $board->getId() . ':calendar', $globalCalendarConfig),
		];
		$board->setSettings($settings);
	}

	public function enrichWithActiveSessions(Board $board) {
		$sessions = $this->sessionMapper->findAllActive($board->getId());

		$board->setActiveSessions(array_values(
			array_unique(
				array_map(function (Session $session) {
					return $session->getUserId();
				}, $sessions)
			)
		));
	}

	/**
	 * @param $boardId
	 * @param $type
	 * @param $participant
	 * @param $edit
	 * @param $share
	 * @param $manage
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 */
	public function addAcl($boardId, $type, $participant, $edit, $share, $manage) {
		$this->boardServiceValidator->check(compact('boardId', 'type', 'participant', 'edit', 'share', 'manage'));

		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_SHARE);
		[$edit, $share, $manage] = $this->applyPermissions($boardId, $edit, $share, $manage);

		$acl = new Acl();
		$acl->setBoardId($boardId);
		$acl->setType($type);
		$acl->setParticipant($participant);
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
		$acl->setPermissionManage($manage);
		$newAcl = $this->aclMapper->insert($acl);

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_BOARD, $newAcl, ActivityManager::SUBJECT_BOARD_SHARE, [], $this->userId);
		$this->notificationHelper->sendBoardShared($boardId, $acl);
		$this->boardMapper->mapAcl($newAcl);
		$this->changeHelper->boardChanged($boardId);

		$board = $this->boardMapper->find($boardId);
		$this->clearBoardFromCache($board);

		// TODO: use the dispatched event for this
		try {
			$resourceProvider = Server::get(\OCA\Deck\Collaboration\Resources\ResourceProvider::class);
			$resourceProvider->invalidateAccessCache($boardId);
		} catch (\Exception $e) {
		}

		$this->eventDispatcher->dispatchTyped(new AclCreatedEvent($acl));

		return $newAcl;
	}

	/**
	 * @param $id
	 * @param $edit
	 * @param $share
	 * @param $manage
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function updateAcl($id, $edit, $share, $manage) {
		$this->boardServiceValidator->check(compact('id', 'edit', 'share', 'manage'));

		$this->permissionService->checkPermission($this->aclMapper, $id, Acl::PERMISSION_SHARE);

		/** @var Acl $acl */
		$acl = $this->aclMapper->find($id);
		[$edit, $share, $manage] = $this->applyPermissions($acl->getBoardId(), $edit, $share, $manage, $acl);
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
		$acl->setPermissionManage($manage);
		$this->boardMapper->mapAcl($acl);
		$board = $this->aclMapper->update($acl);
		$this->changeHelper->boardChanged($acl->getBoardId());

		$this->eventDispatcher->dispatchTyped(new AclUpdatedEvent($acl));

		return $board;
	}

	/**
	 * @throws DbException
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws MultipleObjectsReturnedException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function deleteAcl(int $id): ?Acl {
		$this->permissionService->checkPermission($this->aclMapper, $id, Acl::PERMISSION_MANAGE);
		/** @var Acl $acl */
		$acl = $this->aclMapper->find($id);
		$this->boardMapper->mapAcl($acl);
		if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
			$this->assignedUsersMapper->deleteByParticipantOnBoard($acl->getParticipant(), $acl->getBoardId());
		}

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_BOARD, $acl, ActivityManager::SUBJECT_BOARD_UNSHARE);
		$this->notificationHelper->sendBoardShared($acl->getBoardId(), $acl, true);
		$this->changeHelper->boardChanged($acl->getBoardId());

		$version = \OCP\Util::getVersion()[0];
		if ($version >= 16) {
			try {
				$resourceProvider = Server::get(\OCA\Deck\Collaboration\Resources\ResourceProvider::class);
				$resourceProvider->invalidateAccessCache($acl->getBoardId());
			} catch (\Exception $e) {
			}
		}

		$deletedAcl = $this->aclMapper->delete($acl);
		$this->eventDispatcher->dispatchTyped(new AclDeletedEvent($acl));

		return $deletedAcl;
	}

	/**
	 * @throws BadRequestException
	 * @throws DbException
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoPermissionException
	 */
	public function clone(
		int $id, string $userId,
		bool $withCards = false, bool $withAssignments = false, bool $withLabels = false, bool $withDueDate = false, bool $moveCardsToLeftStack = false, bool $restoreArchivedCards = false,
	): Board {
		$this->boardServiceValidator->check(compact('id', 'userId'));

		if (!$this->permissionService->canCreate()) {
			throw new NoPermissionException('Creating boards has been disabled for your account.');
		}

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_READ);

		$board = $this->boardMapper->find($id);
		$newBoard = new Board();
		$newBoard->setTitle($board->getTitle() . ' (' . $this->l10n->t('copy') . ')');
		$newBoard->setOwner($userId);
		$newBoard->setColor($board->getColor());
		$permissions = $this->permissionService->matchPermissions($board);
		$newBoard->setPermissions([
			'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ] ?? false,
			'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT] ?? false,
			'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE] ?? false,
			'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE] ?? false
		]);
		$this->boardMapper->insert($newBoard);

		foreach ($this->aclMapper->findAll($board->getId()) as $acl) {
			$this->addAcl($newBoard->getId(),
				$acl->getType(),
				$acl->getParticipant(),
				$acl->getPermissionEdit(),
				$acl->getPermissionShare(),
				$acl->getPermissionManage());
		}


		$labels = $this->labelMapper->findAll($id);
		foreach ($labels as $label) {
			$newLabel = new Label();
			$newLabel->setTitle($label->getTitle());
			$newLabel->setColor($label->getColor());
			$newLabel->setBoardId($newBoard->getId());
			$this->labelMapper->insert($newLabel);
		}

		$stacks = $this->stackMapper->findAll($id);
		foreach ($stacks as $stack) {
			$newStack = new Stack();
			$newStack->setTitle($stack->getTitle());
			if ($stack->getOrder() == null) {
				$newStack->setOrder(999);
			} else {
				$newStack->setOrder($stack->getOrder());
			}
			$newStack->setBoardId($newBoard->getId());
			$this->stackMapper->insert($newStack);
		}

		if ($withCards) {
			$this->cloneCards($board, $newBoard, $withAssignments, $withLabels, $withDueDate, $moveCardsToLeftStack, $restoreArchivedCards);
		}

		return $this->find($newBoard->getId());
	}

	public function transferBoardOwnership(int $boardId, string $newOwner, bool $changeContent = false): Board {
		$this->connection->beginTransaction();
		try {
			$board = $this->boardMapper->find($boardId);
			$previousOwner = $board->getOwner();
			$this->clearBoardFromCache($board);
			$this->aclMapper->deleteParticipantFromBoard($boardId, Acl::PERMISSION_TYPE_USER, $newOwner);
			if (!$changeContent) {
				try {
					$this->addAcl($boardId, Acl::PERMISSION_TYPE_USER, $previousOwner, true, true, true);
				} catch (DbException $e) {
					if ($e->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
						throw $e;
					}
				}
			}
			$this->boardMapper->transferOwnership($previousOwner, $newOwner, $boardId);

			// Optionally also change user assignments and card owner information
			if ($changeContent) {
				$this->assignedUsersMapper->remapAssignedUser($boardId, $previousOwner, $newOwner);
				$this->cardMapper->remapCardOwner($boardId, $previousOwner, $newOwner);
			}
			$this->connection->commit();
			return $this->boardMapper->find($boardId);
		} catch (\Throwable $e) {
			$this->connection->rollBack();
			throw $e;
		}
	}

	public function transferOwnership(string $owner, string $newOwner, bool $changeContent = false): \Generator {
		$boards = $this->boardMapper->findAllByUser($owner);
		foreach ($boards as $board) {
			if ($board->getOwner() === $owner) {
				yield $this->transferBoardOwnership($board->getId(), $newOwner, $changeContent);
			}
		}
	}

	/**
	 * @param $id
	 * @return Board
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function export($id) : Board {
		if (is_numeric($id) === false) {
			throw new BadRequestException('board id must be a number');
		}

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_READ);
		$board = $this->boardMapper->find((int)$id);
		$this->enrichWithCards($board);
		$this->enrichWithLabels($board);

		return $board;
	}

	/** @param Board[] $boards */
	private function enrichBoards(array $boards, bool $fullDetails = true): array {
		$result = [];
		foreach ($boards as $board) {
			// FIXME The enrichment in here could make use of combined queries
			$this->boardMapper->mapOwner($board);
			if ($board->getAcl() !== null) {
				foreach ($board->getAcl() as &$acl) {
					$this->boardMapper->mapAcl($acl);
				}
			}

			$permissions = $this->permissionService->matchPermissions($board);
			$board->setPermissions([
				'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ] ?? false,
				'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT] ?? false,
				'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE] ?? false,
				'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE] ?? false
			]);

			if ($fullDetails) {
				$this->enrichWithStacks($board);
				$this->enrichWithLabels($board);
				$this->enrichWithUsers($board);
				$this->enrichWithBoardSettings($board);
				$this->enrichWithActiveSessions($board);
			}

			// Cache for further usage
			if ($fullDetails) {
				$this->boardsCacheFull[$board->getId()] = $board;
			} else {
				$this->boardsCachePartial[$board->getId()] = $board;
			}
		}

		return $boards;
	}

	private function cloneCards(Board $board, Board $newBoard, bool $withAssignments = false, bool $withLabels = false, bool $withDueDate = false, bool $moveCardsToLeftStack = false, bool $restoreArchivedCards = false): void {
		$stacks = $this->stackMapper->findAll($board->getId());
		$newStacks = $this->stackMapper->findAll($newBoard->getId());

		$stackSorter = function (Stack $a, Stack $b) {
			return $a->getOrder() - $b->getOrder();
		};
		usort($stacks, $stackSorter);
		usort($newStacks, $stackSorter);

		$i = 0;
		foreach ($stacks as $stack) {
			$cards = $this->cardMapper->findAll($stack->getId());
			$archivedCards = $this->cardMapper->findAllArchived($stack->getId());

			/** @var Card[] $cards */
			$cards = array_merge($cards, $archivedCards);

			foreach ($cards as $card) {
				$targetStackId = $moveCardsToLeftStack ? $newStacks[0]->getId() : $newStacks[$i]->getId();

				// Create a cloned card.
				// Done with setters as only fields set via setters get written to db
				$newCard = new Card();
				$newCard->setTitle($card->getTitle());
				$newCard->setDescription($card->getDescription());
				$newCard->setStackId($targetStackId);
				$newCard->setType($card->getType());
				$newCard->setOwner($card->getOwner());
				$newCard->setOrder($card->getOrder());
				$newCard->setDuedate($withDueDate ? $card->getDuedate() : null);
				$newCard->setArchived($restoreArchivedCards ? false : $card->getArchived());
				$newCard->setStackId($targetStackId);

				// Persist the cloned card.
				$newCard = $this->cardMapper->insert($newCard);


				// Copy labels.
				if ($withLabels) {
					$labels = $this->labelMapper->findAssignedLabelsForCard($card->getId());
					$newLabels = $this->labelMapper->findAll($newBoard->getId());
					$newLabelTitles = [];
					foreach ($newLabels as $label) {
						$newLabelTitles[$label->getTitle()] = $label;
					}

					foreach ($labels as $label) {
						$newLabelId = $newLabelTitles[$label->getTitle()]?->getId() ?? null;
						if ($newLabelId) {
							$this->cardMapper->assignLabel($newCard->getId(), $newLabelId);
						}
					}
				}


				// Copy assignments.
				if ($withAssignments) {
					$assignments = $this->assignedUsersMapper->findAll($card->getId());

					foreach ($assignments as $assignment) {
						$this->assignmentService->assignUser($newCard->getId(), $assignment->getParticipant(), $assignment->getType());
					}
				}

				// Known limitation: Currently we do not copy attachments or comments

				// Copied from CardService because CardService cannot be injected due to cyclic dependencies.
				$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_CREATE);
				$this->changeHelper->cardChanged($card->getId(), false);
				$this->eventDispatcher->dispatchTyped(new CardCreatedEvent($card));
			}

			$i++;
		}
	}

	private function enrichWithStacks($board, $since = -1) {
		$stacks = $this->stackMapper->findAll($board->getId(), null, null, $since);

		if (\count($stacks) === 0) {
			return;
		}

		$board->setStacks($stacks);
	}

	private function enrichWithLabels($board, $since = -1) {
		$labels = $this->labelMapper->findAll($board->getId(), null, null, $since);

		if (\count($labels) === 0) {
			return;
		}

		$board->setLabels($labels);
	}

	private function enrichWithUsers($board, $since = -1) {
		$boardUsers = $this->permissionService->findUsers($board->getId());
		if ($boardUsers === null || \count($boardUsers) === 0) {
			return;
		}
		$board->setUsers(array_values($boardUsers));
	}

	/**
	 * Clean a given board data from the Cache
	 */
	private function clearBoardFromCache(Board $board) {
		$boardId = $board->getId();
		$boardOwnerId = $board->getOwner();

		$this->boardMapper->flushCache($boardId, $boardOwnerId);
		unset($this->boardsCacheFull[$boardId]);
		unset($this->boardsCachePartial[$boardId]);
	}

	private function enrichWithCards($board) {
		$stacks = $this->stackMapper->findAll($board->getId());
		foreach ($stacks as $stack) {
			$cards = $this->cardMapper->findAllByStack($stack->getId());
			$fullCards = [];
			foreach ($cards as $card) {
				$fullCard = $this->cardMapper->find($card->getId());
				$assignedUsers = $this->assignedUsersMapper->findAll($card->getId());
				$fullCard->setAssignedUsers($assignedUsers);
				array_push($fullCards, $fullCard);
			}
			$stack->setCards($fullCards);
		}

		if (\count($stacks) === 0) {
			return;
		}

		$board->setStacks($stacks);
	}
}
