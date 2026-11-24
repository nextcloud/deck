<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OC\User\LazyUser;
use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Activity\ChangeSet;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\AuditLog\Operation;
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
use OCA\Deck\StatusException;
use OCA\Deck\Validators\BoardServiceValidator;
use OCA\Files_Sharing\Event\UserShareAccessUpdatedEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as DbException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Security\ISecureRandom;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class BoardService {
	/** Settings stored as columns on the board, shared by every member. */
	public const SWIMLANE_SETTINGS = ['swimlaneMode', 'swimlaneLabelOrder', 'swimlaneUserOrder'];

	// Every member is served the stored order, so cap it.
	private const MAX_SWIMLANE_ORDER_BYTES = 32768;
	private const MAX_SWIMLANE_ORDER_ENTRIES = 1000;
	// A user order may arrive this many times over the limit. It is cut down to
	// the entries that belong to the board, the rest of the request is refused.
	private const SWIMLANE_USER_ORDER_INPUT_FACTOR = 2;
	// An order that reaches this share of either limit is logged once.
	private const SWIMLANE_ORDER_WARN_PERCENT = 80;
	// Catch-all lane for cards without a label or an assignee.
	private const SWIMLANE_NONE_ENTRY = '__none__';
	// How a stored order is encoded. Searching for an entry has to use the same flags.
	private const SWIMLANE_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

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
		private readonly ICloudFederationProviderManager $cloudFederationProviderManager,
		private readonly ICloudIdManager $cloudIdManager,
		private readonly ICloudFederationFactory $federationFactory,
		private IEventDispatcher $eventDispatcher,
		private ChangeHelper $changeHelper,
		private IURLGenerator $urlGenerator,
		private IDBConnection $connection,
		private BoardServiceValidator $boardServiceValidator,
		private SessionMapper $sessionMapper,
		private IUserManager $userManager,
		private ISecureRandom $random,
		private ConfigService $configService,
		private CirclesService $circlesService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
	}

	/**
	 * Set a different user than the current one, e.g. when no user is available in occ
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

		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ, null, false);
		$board = $this->boardMapper->find($boardId, true, true, $allowDeleted);
		[$board] = $this->enrichBoards([$board], $fullDetails);
		return $board;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function isArchived($mapper, int $id): bool {
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
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function isDeleted($mapper, int $id): bool {
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
	 * @throws BadRequestException
	 */
	public function create(string $title, string $userId, string $color): Board {
		$this->boardServiceValidator->check(compact('title', 'userId', 'color'));

		if (!$this->permissionService->canCreate()) {
			throw new NoPermissionException('Creating boards has been disabled for your account.');
		}

		$board = new Board();
		$board->setTitle($title);
		$board->setOwner($userId);
		$board->setColor($color);
		/** @var Board $board */
		$board = $this->boardMapper->insert($board);

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
			$label->setBoardId($board->getId());
			$labels[] = $this->labelMapper->insert($label);
		}
		$board->setLabels($labels);
		$this->boardMapper->mapOwner($board);
		$permissions = $this->permissionService->matchPermissions($board);
		$board->setPermissions([
			'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ] ?? false,
			'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT] ?? false,
			'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE] ?? false,
			'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE] ?? false
		]);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_BOARD, $board, ActivityManager::SUBJECT_BOARD_CREATE, [], $userId);
		$this->changeHelper->boardChanged($board->getId());
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
			'Board %s of "%s" was created',
			[
				'boardId' => $board->getId(),
				'owner' => $userId,
			],
			false,
			Operation::BoardCreated->value,
		));

		return $board;
	}

	/**
	 * Create a board with the current user as owner and attach it to a team
	 *
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function createForTeam(string $title, string $userId, ?string $color, string $teamId): Board {
		if ($color === null || $color === '') {
			$color = sprintf('%06x', random_int(0, 0xffffff));
		} elseif (str_starts_with($color, '#')) {
			$color = substr($color, 1);
		}

		$this->boardServiceValidator->check(compact('title', 'userId', 'color'));

		if ($teamId === '') {
			throw new BadRequestException('teamId must not be empty');
		}

		if (!$this->circlesService->isCirclesEnabled()) {
			throw new BadRequestException('Circles/Teams app is not enabled');
		}

		if ($this->circlesService->getCircle($teamId) === null) {
			throw new BadRequestException('Team not found');
		}

		if (!$this->circlesService->isUserInCircle($teamId, $userId)) {
			throw new NoPermissionException('You must be a member of the team to create a team board');
		}

		$board = $this->create($title, $userId, $color);
		$board->setTeamId($teamId);
		$board = $this->boardMapper->update($board);

		// give edit access to the team members
		$this->addAcl(
			$board->getId(),
			Acl::PERMISSION_TYPE_CIRCLE,
			$teamId,
			true,
			false,
			false,
		);

		return $this->find($board->getId());
	}

	/**
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete(int $id): Board {
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
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
			'Board %s was moved to the trash',
			[
				'boardId' => $board->getId(),
			],
			false,
			Operation::BoardDeleted->value,
		));

		if (class_exists(UserShareAccessUpdatedEvent::class)) {
			$acls = $this->aclMapper->findAll($id);
			foreach ($acls as $acl) {
				$user = new LazyUser($acl->getParticipant(), $this->userManager);
				$event = new UserShareAccessUpdatedEvent($user);
				$this->eventDispatcher->dispatchTyped($event);
			}
		}

		return $board;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function deleteUndo(int $id): Board {
		$this->boardServiceValidator->check(compact('id'));

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_MANAGE, allowDeletedBoard: true);
		$board = $this->find($id, allowDeleted: true);
		$board->setDeletedAt(0);
		$board = $this->boardMapper->update($board);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_BOARD, $board, ActivityManager::SUBJECT_BOARD_RESTORE);
		$this->changeHelper->boardChanged($board->getId());
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
			'Board %s was restored from the trash',
			[
				'boardId' => $board->getId(),
			],
			false,
			Operation::BoardRestored->value,
		));

		if (class_exists(UserShareAccessUpdatedEvent::class)) {
			$acls = $this->aclMapper->findAll($id);
			foreach ($acls as $acl) {
				$user = new LazyUser($acl->getParticipant(), $this->userManager);
				$event = new UserShareAccessUpdatedEvent($user);
				$this->eventDispatcher->dispatchTyped($event);
			}
		}

		return $board;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function deleteForce(int $id): Board {
		$this->boardServiceValidator->check(compact('id'));

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_MANAGE, allowDeletedBoard: true);
		$board = $this->find($id, allowDeleted: true);
		$delete = $this->boardMapper->delete($board);

		return $delete;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update(int $id, string $title, string $color, bool $archived): Board {
		$this->boardServiceValidator->check(compact('id', 'title', 'color', 'archived'));

		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_MANAGE);
		$board = $this->find($id);
		$changes = new ChangeSet($board);
		$wasArchived = $board->getArchived();
		$board->setTitle($title);
		$board->setColor($color);
		$board->setArchived($archived);
		$changes->setAfter($board);
		$this->boardMapper->update($board); // operate on clone so we can check for updated fields
		$this->boardMapper->mapOwner($board);
		$this->activityManager->triggerUpdateEvents(ActivityManager::DECK_OBJECT_BOARD, $changes, ActivityManager::SUBJECT_BOARD_UPDATE);
		$this->changeHelper->boardChanged($board->getId());
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($board->getId()));
		if ($wasArchived !== $archived) {
			$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
				$archived ? 'Board %s was archived' : 'Board %s was unarchived',
				['boardId' => $board->getId()],
				false,
				($archived ? Operation::BoardArchived : Operation::BoardUnarchived)->value,
			));
		}

		return $board;
	}

	private function applyPermissions(int $boardId, bool $edit, bool $share, bool $manage, ?Acl $oldAcl = null): array {
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

	/**
	 * Write one of the three swimlane settings onto the board.
	 *
	 * @return string the value as stored, orders in their canonical encoding
	 * @throws BadRequestException if the key is not a swimlane setting or the value is malformed
	 * @throws DoesNotExistException if the board is gone
	 * @throws StatusException if the board is archived
	 */
	public function setSwimlaneSetting(int $boardId, string $key, mixed $value): string {
		if (!in_array($key, self::SWIMLANE_SETTINGS, true)) {
			throw new BadRequestException('Not a swimlane setting: ' . $key);
		}
		// The mode is a plain string, the two orders are lists.
		if ($key === 'swimlaneMode') {
			$mode = $this->validateSwimlaneMode($value);
			$board = $this->findBoardForSwimlaneSetting($boardId);
			$this->persistSwimlaneSetting($board, $key, $mode);
			return $mode;
		}
		$list = $this->normalizeSwimlaneValue($key, $value);

		$board = $this->findBoardForSwimlaneSetting($boardId);
		if ($key === 'swimlaneLabelOrder') {
			$list = $this->dropUnknownLabels($boardId, $list);
		}
		// Read before the setter overwrites it.
		$previous = $key === 'swimlaneLabelOrder' ? $board->getSwimlaneLabelOrder() : $board->getSwimlaneUserOrder();
		$stored = $this->writeSwimlaneSetting($board, $key, $list);
		$this->warnWhenSwimlaneOrderNearsLimit($boardId, $key, $previous, $stored);

		return $stored;
	}

	/**
	 * @throws DoesNotExistException if the board is gone
	 * @throws StatusException if the board is archived
	 */
	private function findBoardForSwimlaneSetting(int $boardId): Board {
		$board = $this->boardMapper->find($boardId);
		if ($board->getArchived()) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		return $board;
	}

	/**
	 * Encode an order and store it. The only place that encodes and checks the
	 * stored size. A user order over the limit loses the entries that have
	 * nothing to do with the board first, a label order is refused.
	 *
	 * @param list<int|string> $list
	 * @return string the canonical encoding that was stored
	 * @throws BadRequestException if the encoded order is too large
	 */
	private function writeSwimlaneSetting(Board $board, string $key, array $list): string {
		$stored = $this->encodeSwimlaneOrder($list);
		$entries = count($list);
		if (!$this->swimlaneOrderFits($stored, $entries) && $key === 'swimlaneUserOrder') {
			$list = $this->dropUnrelatedParticipants($board, $list);
			$bytesBefore = strlen($stored);
			$stored = $this->encodeSwimlaneOrder($list);
			$fits = $this->swimlaneOrderFits($stored, count($list));
			$this->logSwimlaneOrderCutDown($board->getId(), $fits, $entries - count($list), [$bytesBefore, $entries], [strlen($stored), count($list)]);
		}
		if (!$this->swimlaneOrderFits($stored, count($list))) {
			throw new BadRequestException('Swimlane order is too large');
		}
		$this->persistSwimlaneSetting($board, $key, $stored);
		return $stored;
	}

	private function swimlaneOrderFits(string $encoded, int $entries): bool {
		return strlen($encoded) <= self::MAX_SWIMLANE_ORDER_BYTES && $entries <= self::MAX_SWIMLANE_ORDER_ENTRIES;
	}

	/**
	 * Keep the lane for cards without an assignee and every entry of a
	 * participant that is on the board. That is anyone assigned to a card
	 * (archived and deleted cards too), anyone it is shared with and the
	 * owner. The order of the rest does not change.
	 *
	 * @param list<int|string> $list
	 * @return list<int|string>
	 */
	private function dropUnrelatedParticipants(Board $board, array $list): array {
		$related = [];
		$participants = $this->assignedUsersMapper->findParticipantsOnBoard($board->getId());
		foreach ($this->aclMapper->findAll($board->getId()) as $acl) {
			$participants[] = ['type' => $acl->getType(), 'participant' => $acl->getParticipant()];
		}
		if ($board->getOwner() !== null) {
			$participants[] = ['type' => Acl::PERMISSION_TYPE_USER, 'participant' => $board->getOwner()];
		}
		foreach ($participants as $row) {
			$related[self::swimlaneAssigneeKey($row['type'], $row['participant'])] = true;
			if ($row['type'] === Acl::PERMISSION_TYPE_USER) {
				// The client builds the key from the user's own spelling of the id,
				// which can differ from the stored one in case.
				$user = $this->userManager->get($row['participant']);
				if ($user !== null) {
					$related[self::swimlaneAssigneeKey(Acl::PERMISSION_TYPE_USER, $user->getUID())] = true;
				}
			}
		}
		$kept = [];
		foreach ($list as $entry) {
			if ($entry === self::SWIMLANE_NONE_ENTRY || isset($related[$entry])) {
				$kept[] = $entry;
			}
		}
		return $kept;
	}

	/**
	 * Log that a user order was cut down. A cut that makes it fit goes on info,
	 * as an editor can trigger it with every request, and one that does not
	 * goes on warning. Only the board id and numbers, never an entry.
	 *
	 * @param array{int, int} $before bytes and entries before
	 * @param array{int, int} $after bytes and entries after
	 */
	private function logSwimlaneOrderCutDown(int $boardId, bool $fits, int $removed, array $before, array $after): void {
		$context = [
			'boardId' => $boardId,
			'removed' => $removed,
			'bytesBefore' => $before[0],
			'entriesBefore' => $before[1],
			'bytesAfter' => $after[0],
			'entriesAfter' => $after[1],
			'maxBytes' => self::MAX_SWIMLANE_ORDER_BYTES,
			'maxEntries' => self::MAX_SWIMLANE_ORDER_ENTRIES,
		];
		if ($fits) {
			$this->logger->info('Swimlane user order of board ' . $boardId . ' was over its limit, unrelated entries were dropped', $context);
		} else {
			$this->logger->warning('Swimlane user order of board ' . $boardId . ' is still over its limit after dropping unrelated entries', $context);
		}
	}

	/**
	 * Set one swimlane column, move the ETag and dispatch BoardUpdatedEvent.
	 * boardMapper->update writes only the changed field and fires no board
	 * events, so this method calls boardChanged and dispatches the event.
	 */
	private function persistSwimlaneSetting(Board $board, string $key, string $stored): void {
		match ($key) {
			'swimlaneMode' => $board->setSwimlaneMode($stored),
			'swimlaneLabelOrder' => $board->setSwimlaneLabelOrder($stored),
			'swimlaneUserOrder' => $board->setSwimlaneUserOrder($stored),
		};
		$this->boardMapper->update($board);
		$this->changeHelper->boardChanged($board->getId());
		$this->eventDispatcher->dispatchTyped(new BoardUpdatedEvent($board->getId()));
	}

	/**
	 * Log once when an order grows past the warning share of a limit. Only the
	 * setting name and numbers go into the log, never an entry.
	 */
	private function warnWhenSwimlaneOrderNearsLimit(int $boardId, string $key, ?string $previous, string $stored): void {
		[$oldBytes, $oldEntries] = $this->swimlaneOrderSize($previous);
		[$bytes, $entries] = $this->swimlaneOrderSize($stored);
		if ($this->isNearSwimlaneLimit($oldBytes, $oldEntries) || !$this->isNearSwimlaneLimit($bytes, $entries)) {
			return;
		}
		$this->logger->warning('Swimlane order of board ' . $boardId . ' passed ' . self::SWIMLANE_ORDER_WARN_PERCENT . '% of its limit', [
			'boardId' => $boardId,
			'setting' => $key,
			'bytes' => $bytes,
			'entries' => $entries,
			'maxBytes' => self::MAX_SWIMLANE_ORDER_BYTES,
			'maxEntries' => self::MAX_SWIMLANE_ORDER_ENTRIES,
		]);
	}

	/**
	 * Bytes and entries of a stored order. Null and empty count as zero, a value
	 * that is no list counts its bytes and no entries.
	 *
	 * @return array{int, int}
	 */
	private function swimlaneOrderSize(?string $order): array {
		if ($order === null || $order === '') {
			return [0, 0];
		}
		$decoded = json_decode($order, true);
		return [strlen($order), is_array($decoded) ? count($decoded) : 0];
	}

	private function isNearSwimlaneLimit(int $bytes, int $entries): bool {
		return $bytes * 100 >= self::MAX_SWIMLANE_ORDER_BYTES * self::SWIMLANE_ORDER_WARN_PERCENT
			|| $entries * 100 >= self::MAX_SWIMLANE_ORDER_ENTRIES * self::SWIMLANE_ORDER_WARN_PERCENT;
	}

	/**
	 * Remove label ids that are not on the board. A deleted label stays in the
	 * client's order on purpose, so it disappears here with the next save.
	 *
	 * @param list<int|string> $order
	 * @return list<int|string>
	 */
	private function dropUnknownLabels(int $boardId, array $order): array {
		$known = [];
		foreach ($this->labelMapper->findAll($boardId) as $label) {
			$known[$label->getId()] = true;
		}
		$kept = [];
		foreach ($order as $entry) {
			if ($entry === self::SWIMLANE_NONE_ENTRY || isset($known[$entry])) {
				$kept[] = $entry;
			}
		}
		return $kept;
	}

	/**
	 * Canonical encoding of a stored order. The size is checked on the result
	 * by writeSwimlaneSetting, json_encode can make an entry longer (U+2028
	 * grows from 3 to 6 bytes).
	 *
	 * @param list<int|string> $order
	 */
	private function encodeSwimlaneOrder(array $order): string {
		return json_encode($order, self::SWIMLANE_JSON_FLAGS | JSON_THROW_ON_ERROR);
	}

	private function validateSwimlaneMode(mixed $value): string {
		if (!in_array($value, ['none', 'labels', 'assignees'], true)) {
			throw new BadRequestException('Swimlane mode must be one of: none, labels, assignees');
		}
		return $value;
	}

	/**
	 * Every member is served these values, so reject malformed ones. Entries
	 * in the format of earlier builds are dropped, so the lane loses its position once.
	 *
	 * @return list<int|string> the order without legacy entries, not yet encoded
	 */
	private function normalizeSwimlaneValue(string $key, mixed $value): array {
		// swimlaneLabelOrder: JSON array of label ids and the '__none__' catch-all lane.
		// swimlaneUserOrder: JSON array of "<type>:<participant>" and '__none__'.
		// A user order may be larger than the limit, the stored one is cut down later.
		$inputFactor = $key === 'swimlaneUserOrder' ? self::SWIMLANE_USER_ORDER_INPUT_FACTOR : 1;
		if (!is_string($value) || strlen($value) > self::MAX_SWIMLANE_ORDER_BYTES * $inputFactor) {
			throw new BadRequestException('Swimlane order must be a JSON array of lane ids');
		}
		try {
			// Depth 2 allows the list and its scalar entries, nested arrays fail to parse.
			$order = json_decode($value, true, 2, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new BadRequestException('Swimlane order must be a JSON array of lane ids');
		}
		if (!is_array($order) || !array_is_list($order) || count($order) > self::MAX_SWIMLANE_ORDER_ENTRIES * $inputFactor) {
			throw new BadRequestException('Swimlane order must be a JSON array of lane ids');
		}

		$isLabelOrder = $key === 'swimlaneLabelOrder';
		$participantTypes = [
			(string)Acl::PERMISSION_TYPE_USER,
			(string)Acl::PERMISSION_TYPE_GROUP,
			(string)Acl::PERMISSION_TYPE_REMOTE,
			(string)Acl::PERMISSION_TYPE_CIRCLE,
		];
		$seen = [];
		$kept = [];
		foreach ($order as $entry) {
			if ($entry !== self::SWIMLANE_NONE_ENTRY) {
				if ($isLabelOrder) {
					// A string is the format of an earlier build, drop it.
					if (is_string($entry)) {
						continue;
					}
					if (!is_int($entry) || $entry <= 0) {
						throw new BadRequestException('Swimlane label order entries must be label ids or "__none__"');
					}
				} else {
					if (!is_string($entry)) {
						throw new BadRequestException('Swimlane user order entries must be "<type>:<participant>" or "__none__"');
					}
					// The type is digits only, so the first colon splits it from the participant.
					$parts = explode(':', $entry, 2);
					// Without a valid type this is the format of an earlier build, drop it.
					if (count($parts) !== 2 || !in_array($parts[0], $participantTypes, true)) {
						continue;
					}
					// Same rule as when a board is shared: not empty, at most 64 characters.
					$this->boardServiceValidator->check(['participant' => $parts[1]]);
				}
			}
			// Checked after the type, so no entry here is a numeric string.
			if (isset($seen[$entry])) {
				throw new BadRequestException('Swimlane order must not contain duplicates');
			}
			$seen[$entry] = true;
			$kept[] = $entry;
		}
		return $kept;
	}

	/**
	 * Remove a deleted user, group or team from the user order of every board.
	 * A board that fails to update is logged and the next one is processed.
	 */
	public function removeSwimlaneUserKey(int $type, string $participant): void {
		$entry = self::swimlaneAssigneeKey($type, $participant);
		// Same encoding as the stored value, so the search finds the entry.
		$needle = json_encode($entry, self::SWIMLANE_JSON_FLAGS);
		if ($needle === false) {
			// Invalid UTF-8 cannot be encoded, so no stored order holds this entry.
			return;
		}
		foreach ($this->boardMapper->findIdsWithSwimlaneUserOrderEntry($needle) as $boardId) {
			try {
				$this->dropSwimlaneUserEntry($boardId, $entry);
			} catch (DoesNotExistException) {
				// Removed since the search.
			} catch (\Throwable $e) {
				$this->logger->warning('Could not remove a deleted participant from the swimlane order of board ' . $boardId, [
					'boardId' => $boardId,
					'exception' => $e,
				]);
			}
		}
	}

	/**
	 * Works on archived and deleted boards too. The search is a substring
	 * match, so the entry is compared exactly here.
	 *
	 * @throws DoesNotExistException if the board is gone
	 */
	private function dropSwimlaneUserEntry(int $boardId, string $entry): void {
		// Another request may have changed the board since it was cached.
		$this->boardMapper->flushCache($boardId);
		$board = $this->boardMapper->find($boardId, false, false, true);
		$stored = $board->getSwimlaneUserOrder();
		if ($stored === null) {
			return;
		}
		$order = json_decode($stored, true);
		if (!is_array($order)) {
			return;
		}
		$kept = [];
		foreach ($order as $item) {
			if ($item !== $entry) {
				$kept[] = $item;
			}
		}
		if (count($kept) === count($order)) {
			return;
		}
		$this->writeSwimlaneSetting($board, 'swimlaneUserOrder', $kept);
	}

	/**
	 * Entry of an assignee lane in swimlaneUserOrder. The type keeps a user
	 * and a group with the same id apart.
	 */
	public static function swimlaneAssigneeKey(int $type, string $participant): string {
		return $type . ':' . $participant;
	}

	public function enrichWithBoardSettings(Board $board): void {
		$globalCalendarConfig = (bool)$this->config->getUserValue($this->userId, Application::APP_ID, 'calendar', true);
		$boardId = $board->getId();
		$settings = [
			'notify-due' => $this->config->getUserValue($this->userId, Application::APP_ID, 'board:' . $boardId . ':notify-due', ConfigService::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED),
			'calendar' => $this->config->getUserValue($this->userId, Application::APP_ID, 'board:' . $boardId . ':calendar', $globalCalendarConfig),
			// Shared by every member. Null until someone changes them.
			'swimlaneMode' => $board->getSwimlaneMode() ?? 'none',
			'swimlaneLabelOrder' => $board->getSwimlaneLabelOrder() ?? '[]',
			'swimlaneUserOrder' => $board->getSwimlaneUserOrder() ?? '[]',
		];
		$board->setSettings($settings);
	}

	public function enrichWithActiveSessions(Board $board): void {
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
	 * @param Acl::PERMISSION_TYPE_* $type
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 */
	public function addAcl(int $boardId, int $type, $participant, bool $edit, bool $share, bool $manage): Acl {
		$this->boardServiceValidator->check(compact('boardId', 'type', 'participant', 'edit', 'share', 'manage'));

		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_SHARE);
		[$edit, $share, $manage] = $this->applyPermissions($boardId, $edit, $share, $manage);

		$acl = new Acl();
		if ($type === Acl::PERMISSION_TYPE_REMOTE) {
			$this->configService->ensureFederationEnabled();
			$sharedBy = $this->userManager->get($this->userId);
			$board = $this->find($boardId);
			$token = $this->random->generate(32);
			$cloudShare = $this->federationFactory->getCloudFederationShare(
				$participant,
				$board->getTitle(),
				'',
				(string)$boardId,
				$sharedBy->getCloudId(),
				$sharedBy->getDisplayName(),
				$sharedBy->getCloudId(),
				$sharedBy->getDisplayName(),
				$token,
				'user',
				'deck'
			);
			$this->cloudFederationProviderManager->sendCloudShare($cloudShare);
			$acl->setToken($token);
		}

		$acl->setBoardId($boardId);
		$acl->setType($type);
		$acl->setParticipant($participant);
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
		$acl->setPermissionManage($manage);
		$now = time();
		$acl->setCreatedAt($now);
		$acl->setLastModifiedAt($now);
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
		$this->logBoardShare($newAcl, 'created', Operation::BoardShareCreated);

		// Sync permissions to remote server since shareReceived() creates ACL with no permissions
		if ($type === Acl::PERMISSION_TYPE_REMOTE && ($edit || $share || $manage)) {
			$notification = $this->federationFactory->getCloudFederationNotification();
			$payload = [
				$newAcl->jsonSerialize(),
				'sharedSecret' => $newAcl->getToken(),
			];
			$notification->setMessage('update-permissions', 'deck', (string)$boardId, $payload);
			$url = $this->cloudIdManager->resolveCloudId($participant);
			$this->cloudFederationProviderManager->sendCloudNotification($url->getRemote(), $notification);
		}

		return $newAcl;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function updateAcl(int $id, bool $edit, bool $share, bool $manage): Acl {
		$this->boardServiceValidator->check(compact('id', 'edit', 'share', 'manage'));

		$this->permissionService->checkPermission($this->aclMapper, $id, Acl::PERMISSION_SHARE);

		/** @var Acl $acl */
		$acl = $this->aclMapper->find($id);
		[$edit, $share, $manage] = $this->applyPermissions($acl->getBoardId(), $edit, $share, $manage, $acl);
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
		$acl->setPermissionManage($manage);
		$acl->setLastModifiedAt(time());
		$this->boardMapper->mapAcl($acl);
		$acl = $this->aclMapper->update($acl);
		$this->changeHelper->boardChanged($acl->getBoardId());

		if ($acl->getType() === Acl::PERMISSION_TYPE_REMOTE) {
			$this->configService->ensureFederationEnabled();
			$notification = $this->federationFactory->getCloudFederationNotification();

			$payload = [
				$acl->jsonSerialize(),
				'sharedSecret' => $acl->getToken(),
			];

			$notification->setMessage('update-permissions', 'deck', (string)$acl->getBoardId(), $payload);

			$url = $this->cloudIdManager->resolveCloudId($acl->getParticipant());
			$this->cloudFederationProviderManager->sendCloudNotification($url->getRemote(), $notification);
		}

		$this->eventDispatcher->dispatchTyped(new AclUpdatedEvent($acl));
		$this->logBoardShare($acl, 'updated', Operation::BoardShareUpdated);

		return $acl;
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
		$this->logBoardShare($acl, 'removed', Operation::BoardShareDeleted);

		return $deletedAcl;
	}

	public function leave(int $boardId): ?Acl {
		if ($this->permissionService->userIsBoardOwner($boardId)) {
			throw new BadRequestException('Board owner cannot leave board');
		}

		$acl = $this->aclMapper->findParticipantFromBoard($boardId, Acl::PERMISSION_TYPE_USER, $this->userId);

		if (!$acl) {
			throw new BadRequestException('Not a participant of this board');
		}

		$this->assignedUsersMapper->deleteByParticipantOnBoard($acl->getParticipant(), $acl->getBoardId());

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_BOARD, $acl, ActivityManager::SUBJECT_BOARD_UNSHARE);
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
		$this->logBoardShare($acl, 'left', Operation::BoardShareLeft);

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
			if ($stack->getOrder() === null) {
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
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
			'Board %s was cloned into board %s of "%s"',
			[
				'sourceBoardId' => $board->getId(),
				'boardId' => $newBoard->getId(),
				'owner' => $userId,
			],
			false,
			Operation::BoardCloned->value,
		));

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
			$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
				'Ownership of board %s was transferred from "%s" to "%s"',
				[
					'boardId' => $boardId,
					'previousOwner' => $previousOwner,
					'newOwner' => $newOwner,
					'changeContent' => $changeContent,
				],
				false,
				Operation::BoardOwnershipTransferred->value,
			));
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
	 * @throws DoesNotExistException
	 * @throws NoPermissionException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function export(int $id): Board {
		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_READ);
		$board = $this->boardMapper->find($id);
		$this->enrichWithCards($board);
		$this->enrichWithLabels($board);

		return $board;
	}

	/**
	 * @param Board[] $boards
	 * @return Board[]
	 */
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

		$stackIds = array_map(fn (Stack $stack) => $stack->getId(), $stacks);
		$activeCardsByStack = $this->cardMapper->findAllForStacks($stackIds);
		$archivedCardsByStack = $this->cardMapper->findAllArchivedForStacks($stackIds);

		$i = 0;
		foreach ($stacks as $stack) {
			/** @var Card[] $cards */
			$cards = array_merge($activeCardsByStack[$stack->getId()] ?? [], $archivedCardsByStack[$stack->getId()] ?? []);

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

	private function enrichWithStacks(Board $board): void {
		$stacks = $this->stackMapper->findAll($board->getId());

		if (\count($stacks) === 0) {
			return;
		}

		$board->setStacks($stacks);
	}

	private function enrichWithLabels(Board $board): void {
		$labels = $this->labelMapper->findAll($board->getId());

		if (\count($labels) === 0) {
			return;
		}

		$board->setLabels($labels);
	}

	private function enrichWithUsers(Board $board): void {
		$boardUsers = $this->permissionService->findUsers($board->getId());
		if ($boardUsers === null || \count($boardUsers) === 0) {
			return;
		}
		$board->setUsers(array_values($boardUsers));
	}

	/**
	 * Clean a given board data from the Cache
	 */
	private function logBoardShare(Acl $acl, string $action, Operation $operation): void {
		$permissions = ['read'];
		if ($acl->getPermissionEdit()) {
			$permissions[] = 'edit';
		}
		if ($acl->getPermissionShare()) {
			$permissions[] = 'share';
		}
		if ($acl->getPermissionManage()) {
			$permissions[] = 'manage';
		}

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
			'Share %s of board %s with %s "%s" was ' . $action . ' (permissions: %s)',
			[
				'aclId' => $acl->getId(),
				'boardId' => $acl->getBoardId(),
				'shareType' => match ($acl->getType()) {
					Acl::PERMISSION_TYPE_USER => 'user',
					Acl::PERMISSION_TYPE_GROUP => 'group',
					Acl::PERMISSION_TYPE_REMOTE => 'remote user',
					Acl::PERMISSION_TYPE_CIRCLE => 'team',
					default => 'unknown',
				},
				'sharedWith' => $acl->getParticipant(),
				'permissions' => implode(', ', $permissions),
			],
			false,
			$operation->value,
		));
	}

	private function clearBoardFromCache(Board $board): void {
		$boardId = $board->getId();
		$boardOwnerId = $board->getOwner();

		$this->boardMapper->flushCache($boardId, $boardOwnerId);
		unset($this->boardsCacheFull[$boardId]);
		unset($this->boardsCachePartial[$boardId]);
	}

	private function enrichWithCards(Board $board): void {
		$stacks = $this->stackMapper->findAll($board->getId());
		if (\count($stacks) === 0) {
			return;
		}

		$stackIds = array_map(fn (Stack $stack) => $stack->getId(), $stacks);

		// Fetch all active cards for all stacks in one query
		$cardsByStack = $this->cardMapper->findAllForStacks($stackIds);

		$allCards = array_merge(...array_values(array_filter($cardsByStack)));
		$allCardIds = array_map(fn (Card $card) => $card->getId(), $allCards);

		// Batch-fetch labels and assigned users for all cards
		$labelsByCard = [];
		foreach ($this->labelMapper->findAssignedLabelsForCards($allCardIds) as $label) {
			$labelsByCard[$label->getCardId()][] = $label;
		}
		$usersByCard = [];
		foreach ($this->assignedUsersMapper->findIn($allCardIds) as $assignment) {
			$usersByCard[$assignment->getCardId()][] = $assignment;
		}

		foreach ($stacks as $stack) {
			$fullCards = [];
			foreach ($cardsByStack[$stack->getId()] ?? [] as $card) {
				$card->setLabels($labelsByCard[$card->getId()] ?? []);
				$card->setAssignedUsers($usersByCard[$card->getId()] ?? []);
				$fullCards[] = $card;
			}
			$stack->setCards($fullCards);
		}

		$board->setStacks($stacks);
	}
}
