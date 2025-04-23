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

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Activity\ChangeSet;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
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
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\Validators\BoardServiceValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as DbException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class BoardService {
	private BoardMapper $boardMapper;
	private StackMapper $stackMapper;
	private LabelMapper $labelMapper;
	private AclMapper $aclMapper;
	private IConfig $config;
	private IL10N $l10n;
	private PermissionService $permissionService;
	private NotificationHelper $notificationHelper;
	private AssignmentMapper $assignedUsersMapper;
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private ?string $userId;
	private ActivityManager $activityManager;
	private IEventDispatcher $eventDispatcher;
	private ChangeHelper $changeHelper;
	private CardMapper $cardMapper;
	private ?array $boardsCacheFull = null;
	private ?array $boardsCachePartial = null;
	private IURLGenerator $urlGenerator;
	private IDBConnection $connection;
	private BoardServiceValidator $boardServiceValidator;
	private SessionMapper $sessionMapper;

	public function __construct(
		BoardMapper $boardMapper,
		StackMapper $stackMapper,
		CardMapper $cardMapper,
		IConfig $config,
		IL10N $l10n,
		LabelMapper $labelMapper,
		AclMapper $aclMapper,
		PermissionService $permissionService,
		NotificationHelper $notificationHelper,
		AssignmentMapper $assignedUsersMapper,
		IUserManager $userManager,
		IGroupManager $groupManager,
		ActivityManager $activityManager,
		IEventDispatcher $eventDispatcher,
		ChangeHelper $changeHelper,
		IURLGenerator $urlGenerator,
		IDBConnection $connection,
		BoardServiceValidator $boardServiceValidator,
		SessionMapper $sessionMapper,
		?string $userId
	) {
		$this->boardMapper = $boardMapper;
		$this->stackMapper = $stackMapper;
		$this->cardMapper = $cardMapper;
		$this->labelMapper = $labelMapper;
		$this->config = $config;
		$this->aclMapper = $aclMapper;
		$this->l10n = $l10n;
		$this->permissionService = $permissionService;
		$this->notificationHelper = $notificationHelper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->activityManager = $activityManager;
		$this->eventDispatcher = $eventDispatcher;
		$this->changeHelper = $changeHelper;
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->connection = $connection;
		$this->boardServiceValidator = $boardServiceValidator;
		$this->sessionMapper = $sessionMapper;
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
		if ($this->boardsCacheFull && $fullDetails) {
			return $this->boardsCacheFull;
		}

		if ($this->boardsCachePartial && !$fullDetails) {
			return $this->boardsCachePartial;
		}

		$complete = $this->getUserBoards($since, $includeArchived);
		return $this->enrichBoards($complete, $fullDetails);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws \OCA\Deck\NoPermissionException
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
	 * @throws \OCA\Deck\NoPermissionException
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
	 * @throws \OCA\Deck\NoPermissionException
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
	 * @throws \OCA\Deck\NoPermissionException
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
	 * @throws \OCA\Deck\NoPermissionException
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
	 * @throws \OCA\Deck\NoPermissionException
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
	 * @throws \OCA\Deck\NoPermissionException
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

	private function applyPermissions($boardId, $edit, $share, $manage) {
		try {
			$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_MANAGE);
		} catch (NoPermissionException $e) {
			$acls = $this->aclMapper->findAll($boardId);
			$edit = $this->permissionService->userCan($acls, Acl::PERMISSION_EDIT, $this->userId) && $edit;
			$share = $this->permissionService->userCan($acls, Acl::PERMISSION_SHARE, $this->userId) && $share;
			$manage = $this->permissionService->userCan($acls, Acl::PERMISSION_MANAGE, $this->userId) && $manage;
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
	 * @throws \OCA\Deck\NoPermissionException
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
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function updateAcl($id, $edit, $share, $manage) {
		$this->boardServiceValidator->check(compact('id', 'edit', 'share', 'manage'));

		$this->permissionService->checkPermission($this->aclMapper, $id, Acl::PERMISSION_SHARE);

		/** @var Acl $acl */
		$acl = $this->aclMapper->find($id);
		[$edit, $share, $manage] = $this->applyPermissions($acl->getBoardId(), $edit, $share, $manage);
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
		$this->permissionService->checkPermission($this->aclMapper, $id, Acl::PERMISSION_SHARE);
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
	 * @param $id
	 * @param $userId
	 * @return Board
	 * @throws DoesNotExistException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function clone($id, $userId) {
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
			$newStack->setBoardId($newBoard->getId());
			$this->stackMapper->insert($newStack);
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
	 * @throws \OCA\Deck\NoPermissionException
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

	public function getBoardUrl($endpoint) {
		return $this->urlGenerator->linkToRouteAbsolute('deck.page.index') . '#' . $endpoint;
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
