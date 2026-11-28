<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

use OCA\Deck\AuditLog\Operation;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\ImportRef;
use OCA\Deck\Db\ImportRefMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\Event\AclUpdatedEvent;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Listeners\AclCreatedRemovedListener;
use OCA\Deck\Listeners\FullTextSearchEventListener;
use OCA\Deck\Listeners\ResourceListener;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Sharing\DeckShareProvider;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * The board import API, written on Deck's mappers
 *
 * Deck's services announce every change through activities, notifications
 * and events (which reach notify_push and other apps), so the importer
 * writes through the mappers and only runs the side effects that keep
 * derived state right: full-text search, collaboration resources, share
 * access, change etags and the admin audit log.
 */
class BoardImporter implements IBoardImporter {
	use TTransactional;

	private const MAX_REF_BYTES = 255;
	private const MAX_BOARD_TITLE = 100;
	private const MAX_STACK_TITLE = 100;
	private const MAX_LABEL_TITLE = 100;
	private const MAX_LIST_LIMIT = 1000;
	/** Deck's IDs are 32-bit integers */
	private const MAX_DECK_ID = 2147483647;

	public function __construct(
		private IDBConnection $db,
		private ImportRefMapper $refMapper,
		private BoardMapper $boardMapper,
		private StackMapper $stackMapper,
		private LabelMapper $labelMapper,
		private CardMapper $cardMapper,
		private AssignmentMapper $assignmentMapper,
		private AclMapper $aclMapper,
		private ChangeHelper $changeHelper,
		private PermissionService $permissionService,
		private DeckShareProvider $shareProvider,
		private IShareManager $shareManager,
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private FullTextSearchEventListener $fullTextSearch,
		private AclCreatedRemovedListener $shareAccess,
		private ResourceListener $resources,
		private IEventDispatcher $eventDispatcher,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function ensureBoard(string $app, string $ref, string $ownerUid, string $title, string $color,
		\DateTimeImmutable $created, array $labels, array $stacks): array {
		try {
			$this->checkApp($app);
			$this->checkRef('ref', $ref);
			$this->checkTitle('title', $title, self::MAX_BOARD_TITLE);
			$this->checkColor('color', $color);
			$this->checkLabels($labels);
			$this->checkStacks($stacks);
			if (!$this->userManager->userExists($ownerUid)) {
				throw EntryException::error(ImportResult::ERROR_UNKNOWN_USER, 'The owner is not a user');
			}
		} catch (EntryException $e) {
			return [
				'status' => ImportResult::STATUS_ERROR,
				'board_id' => 0,
				'created' => false,
				'labels' => [],
				'stacks' => [],
				'changed' => [],
				'error' => ['code' => (string)$e->result->errorCode, 'message' => $e->getMessage()],
			];
		}

		$result = $this->retryOnUniqueViolation(fn () => $this->atomic(
			fn () => $this->writeBoard($app, $ref, $ownerUid, $title, $color, $labels, $stacks),
			$this->db,
		));
		if ($result['created']) {
			$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(
				'Board %s of "%s" was imported by %s',
				[
					'boardId' => $result['board_id'],
					'owner' => $ownerUid,
					'importSystem' => $app,
				],
				false,
				Operation::BoardImported->value,
			));
		}
		return $result;
	}

	/**
	 * @param list<ImportLabel> $labels
	 * @param list<ImportStack> $stacks
	 * @return array{status: string, board_id: int, created: bool, labels: array<string, int>, stacks: array<string, int>, changed: list<string>, error?: array{code: string, message: string}}
	 */
	private function writeBoard(string $app, string $ref, string $ownerUid, string $title, string $color, array $labels, array $stacks): array {
		$now = $this->timeFactory->getTime();
		$changed = [];
		$created = false;
		$wrote = false;
		$boardRef = $this->refMapper->findByRef($app, ImportRef::KIND_BOARD, 0, $ref);
		if ($boardRef === null) {
			$board = new Board();
			$board->setTitle($title);
			$board->setOwner($ownerUid);
			$board->setColor($color);
			$board->setArchived(false);
			$board->setDeletedAt(0);
			$board->setLastModified($now);
			$board = $this->boardMapper->insert($board);
			$this->insertRef($app, ImportRef::KIND_BOARD, 0, $ref, $board->getId(), self::valuesHash($title, $color));
			$created = true;
			$wrote = true;
		} else {
			try {
				$board = $this->boardMapper->find($boardRef->getObjectId(), false, false, true);
			} catch (DoesNotExistException) {
				$board = null;
			}
			if ($board === null || $board->getDeletedAt() > 0) {
				return [
					'status' => ImportResult::STATUS_CONFLICT,
					'board_id' => $boardRef->getObjectId(),
					'created' => false,
					'labels' => [],
					'stacks' => [],
					'changed' => [$ref],
					'error' => ['code' => ImportResult::ERROR_CHANGED, 'message' => 'The board was deleted in Deck'],
				];
			}
			$current = self::valuesHash($board->getTitle(), $board->getColor());
			if ($current !== $boardRef->getStoredHash()) {
				$changed[] = $ref;
			} elseif ($current !== self::valuesHash($title, $color)) {
				$board->setTitle($title);
				$board->setColor($color);
				$board->setLastModified($now);
				$this->boardMapper->update($board);
				$this->updateRef($boardRef, self::valuesHash($title, $color));
				$wrote = true;
			}
		}
		$boardId = $board->getId();

		[$labelIds, $labelsChanged, $labelsWritten] = $this->ensureLabels($app, $boardId, $labels);
		[$stackIds, $stacksChanged, $stacksWritten] = $this->ensureStacks($app, $boardId, $stacks);

		if ($wrote || $labelsWritten || $stacksWritten) {
			$this->boardMapper->flushCache($boardId);
			$this->changeHelper->boardChanged($boardId);
		}

		return [
			'status' => ImportResult::STATUS_OK,
			'board_id' => $boardId,
			'created' => $created,
			'labels' => $labelIds,
			'stacks' => $stackIds,
			'changed' => array_merge($changed, $labelsChanged, $stacksChanged),
		];
	}

	/**
	 * @param list<ImportLabel> $labels
	 * @return array{0: array<string, int>, 1: list<string>, 2: bool} the label IDs by ref, the refs whose Deck version was kept and whether a label was written
	 */
	private function ensureLabels(string $app, int $boardId, array $labels): array {
		$refs = $this->refMapper->findByRefs($app, ImportRef::KIND_LABEL, $boardId, array_map(fn (ImportLabel $label) => $label->ref, $labels));
		$existing = [];
		foreach ($this->labelMapper->findAll($boardId) as $label) {
			$existing[$label->getId()] = $label;
		}

		$ids = [];
		$changed = [];
		$wrote = false;
		foreach ($labels as $wanted) {
			$labelRef = $refs[$wanted->ref] ?? null;
			if ($labelRef === null) {
				$adopted = null;
				foreach ($existing as $label) {
					if ($label->getTitle() === $wanted->title) {
						$adopted = $label;
						break;
					}
				}
				if ($adopted !== null) {
					// A label somebody made with the same title is used as it is
					$this->insertRef($app, ImportRef::KIND_LABEL, $boardId, $wanted->ref, $adopted->getId(), '');
					$ids[$wanted->ref] = $adopted->getId();
					$changed[] = $wanted->ref;
					$wrote = true;
					continue;
				}
				$label = new Label();
				$label->setTitle($wanted->title);
				$label->setColor($wanted->color);
				$label->setBoardId($boardId);
				$label = $this->labelMapper->insert($label);
				$existing[$label->getId()] = $label;
				$this->insertRef($app, ImportRef::KIND_LABEL, $boardId, $wanted->ref, $label->getId(), self::valuesHash($wanted->title, $wanted->color));
				$ids[$wanted->ref] = $label->getId();
				$wrote = true;
				continue;
			}

			$label = $existing[$labelRef->getObjectId()] ?? null;
			if ($label === null) {
				$changed[] = $wanted->ref;
				continue;
			}
			$ids[$wanted->ref] = $label->getId();
			$current = self::valuesHash($label->getTitle(), $label->getColor());
			$next = self::valuesHash($wanted->title, $wanted->color);
			if ($current !== $labelRef->getStoredHash()
				|| ($label->getTitle() !== $wanted->title && $this->labelMapper->existsByBoardIdAndTitle($boardId, $wanted->title, $label->getId()))) {
				$changed[] = $wanted->ref;
			} elseif ($current !== $next) {
				$label->setTitle($wanted->title);
				$label->setColor($wanted->color);
				$this->labelMapper->update($label);
				$this->updateRef($labelRef, $next);
				$wrote = true;
			}
		}
		return [$ids, $changed, $wrote];
	}

	/**
	 * @param list<ImportStack> $stacks
	 * @return array{0: array<string, int>, 1: list<string>, 2: bool} the stack IDs by ref, the refs whose Deck version was kept and whether a stack was written
	 */
	private function ensureStacks(string $app, int $boardId, array $stacks): array {
		$refs = $this->refMapper->findByRefs($app, ImportRef::KIND_STACK, $boardId, array_map(fn (ImportStack $stack) => $stack->ref, $stacks));
		$existing = [];
		foreach ($this->stackMapper->findAll($boardId) as $stack) {
			$existing[$stack->getId()] = $stack;
		}

		$ids = [];
		$changed = [];
		$wrote = false;
		foreach ($stacks as $wanted) {
			$stackRef = $refs[$wanted->ref] ?? null;
			if ($stackRef === null) {
				$stack = new Stack();
				$stack->setTitle($wanted->title);
				$stack->setBoardId($boardId);
				$stack->setOrder($wanted->order);
				$stack->setLastModified($this->timeFactory->getTime());
				$stack = $this->stackMapper->insert($stack);
				$this->insertRef($app, ImportRef::KIND_STACK, $boardId, $wanted->ref, $stack->getId(), self::valuesHash($wanted->title, $wanted->order));
				$ids[$wanted->ref] = $stack->getId();
				$wrote = true;
				continue;
			}

			$stack = $existing[$stackRef->getObjectId()] ?? null;
			if ($stack === null) {
				$changed[] = $wanted->ref;
				continue;
			}
			$ids[$wanted->ref] = $stack->getId();
			$current = self::valuesHash($stack->getTitle(), $stack->getOrder());
			$next = self::valuesHash($wanted->title, $wanted->order);
			if ($current !== $stackRef->getStoredHash()) {
				$changed[] = $wanted->ref;
			} elseif ($current !== $next) {
				$stack->setTitle($wanted->title);
				$stack->setOrder($wanted->order);
				$stack->setLastModified($this->timeFactory->getTime());
				$this->stackMapper->update($stack);
				$this->updateRef($stackRef, $next);
				$wrote = true;
			}
		}
		return [$ids, $changed, $wrote];
	}

	#[\Override]
	public function importCards(string $app, int $boardId, array $cards): array {
		$context = $this->context($app, $boardId);
		$seen = [];
		$results = [];
		foreach ($cards as $card) {
			try {
				if (!$card instanceof ImportCard) {
					throw EntryException::error(ImportResult::ERROR_INVALID, 'Not a card');
				}
				$this->checkCard($card, $context, $seen);
				$results[] = $this->importCard($app, $context, $card);
			} catch (EntryException $e) {
				$results[] = $e->result;
			}
		}
		return $results;
	}

	/**
	 * Writes the card with its labels and assignments in one transaction,
	 * then its attachments: sharing a file runs the server's share and
	 * mount listeners, which must not run inside a transaction (a failed
	 * statement they recover from aborts it on PostgreSQL). Until the
	 * attachments are written the reference has no source hash, so a
	 * repeat completes the card.
	 *
	 * @throws EntryException
	 */
	private function importCard(string $app, BoardContext $context, ImportCard $card): ImportResult {
		$cardRef = $this->refMapper->findByRef($app, ImportRef::KIND_CARD, $context->board->getId(), $card->ref);
		$attached = $cardRef === null ? [] : $this->cardFiles($cardRef->getObjectId());
		$files = $this->resolveFiles($context->board, array_values(array_diff($card->files, array_keys($attached))));

		$write = $this->retryOnUniqueViolation(fn () => $this->atomic(
			fn () => $this->writeCard($app, $context, $card),
			$this->db,
		));
		if ($write instanceof ImportResult) {
			return $write;
		}
		[$entity, $cardRef, $created] = $write;
		$cardId = $entity->getId();

		try {
			$this->writeFiles($context->board, $cardId, $card->files, $files);
		} catch (EntryException $e) {
			$stored = $this->storedHash($context, $card->ref, $this->cardMapper->find($cardId, false));
			$this->updateRef($cardRef, $stored);
			throw new EntryException(new ImportResult(ImportResult::STATUS_ERROR, $cardId, $stored, $e->result->errorCode, $e->result->errorMessage));
		}

		$stored = $this->storedHash($context, $card->ref, $this->cardMapper->find($cardId, false));
		$this->updateRef($cardRef, $stored, $card->sha256);
		$this->changeHelper->cardChanged($cardId, false);
		$this->fullTextSearch->handle($created ? new CardCreatedEvent($entity) : new CardUpdatedEvent($entity));
		return ImportResult::ok($cardId, $stored);
	}

	/**
	 * @param array<string, true> $seen the refs of the call so far
	 * @throws EntryException
	 */
	private function checkCard(ImportCard $card, BoardContext $context, array &$seen): void {
		$this->checkRef('ref', $card->ref);
		if (isset($seen[$card->ref])) {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The ref appears twice');
		}
		$seen[$card->ref] = true;
		if (!isset($context->stackIds[$card->stackRef])) {
			throw EntryException::error(ImportResult::ERROR_UNKNOWN_STACK, 'The stack is not on the board');
		}
		$this->checkTitle('title', $card->title, Card::TITLE_MAX_LENGTH);
		if ($card->mode !== ImportCard::MODE_CREATE && $card->mode !== ImportCard::MODE_NEW_VERSION) {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The mode is invalid');
		}
		if (preg_match('/^[0-9a-f]{64}$/', $card->sha256) !== 1) {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The sha256 is not a lowercase hex SHA-256');
		}
		if ($card->createdBy !== '' && !$this->userManager->userExists($card->createdBy)) {
			throw EntryException::error(ImportResult::ERROR_UNKNOWN_USER, 'The creator is not a user');
		}
		$this->checkList('assignees', $card->assignees, 'is_string');
		foreach ($card->assignees as $uid) {
			if (!$this->userManager->userExists($uid)) {
				throw EntryException::error(ImportResult::ERROR_UNKNOWN_USER, 'An assignee is not a user');
			}
		}
		$this->checkList('labels', $card->labels, 'is_string');
		foreach ($card->labels as $labelRef) {
			if (!isset($context->labelIds[$labelRef])) {
				throw EntryException::error(ImportResult::ERROR_UNKNOWN_LABEL, 'A label is not on the board');
			}
		}
		$this->checkList('files', $card->files, fn ($id) => is_int($id) && $id > 0);
	}

	/**
	 * Writes the card, its labels and assignments, or answers without a write
	 *
	 * @return ImportResult|array{0: Card, 1: ImportRef, 2: bool} the result, or the written card, its reference and whether it is new
	 * @throws EntryException
	 */
	private function writeCard(string $app, BoardContext $context, ImportCard $card): ImportResult|array {
		$boardId = $context->board->getId();
		$cardRef = $this->refMapper->findByRef($app, ImportRef::KIND_CARD, $boardId, $card->ref);
		if ($cardRef === null) {
			if ($card->mode === ImportCard::MODE_NEW_VERSION) {
				throw EntryException::error(ImportResult::ERROR_NOT_FOUND, 'The card was not imported');
			}
			$entity = $this->insertCard($context, $card);
			$cardRef = $this->insertRef($app, ImportRef::KIND_CARD, $boardId, $card->ref, $entity->getId(), null);
			$this->writeLabelsAndAssignees($context, $entity->getId(), $card);
			return [$entity, $cardRef, true];
		}

		$entity = $this->findCard($cardRef->getObjectId());
		if ($entity === null) {
			throw EntryException::conflict(ImportResult::ERROR_CHANGED, 'The card was deleted in Deck', $cardRef->getObjectId());
		}
		$current = $this->storedHash($context, $card->ref, $entity);
		if ($cardRef->getState() === ImportRef::STATE_REMOVED) {
			if ($entity->getDeletedAt() === 0 || $current !== $cardRef->getStoredHash()) {
				throw EntryException::conflict(ImportResult::ERROR_CHANGED, 'The card was restored or changed in Deck', $entity->getId(), $current);
			}
			$entity->setDeletedAt(0);
		} else {
			if ($this->changedInDeck($entity, $current, $cardRef)) {
				throw EntryException::conflict(ImportResult::ERROR_CHANGED, 'The card was changed in Deck', $entity->getId(), $current);
			}
			if ($card->sha256 === $cardRef->getSourceHash()) {
				return ImportResult::ok($entity->getId(), $current);
			}
			if ($card->mode === ImportCard::MODE_CREATE && $cardRef->getSourceHash() !== null) {
				throw EntryException::conflict(ImportResult::ERROR_EXISTS, 'The card exists with other content', $entity->getId(), $current);
			}
		}

		$this->updateCard($context, $entity, $card);
		$this->writeLabelsAndAssignees($context, $entity->getId(), $card);
		$cardRef->setState(ImportRef::STATE_ACTIVE);
		$cardRef->setSourceHash(null);
		$cardRef->setUpdatedAt($this->timeFactory->getTime());
		$this->refMapper->update($cardRef);
		return [$entity, $cardRef, false];
	}

	private function insertCard(BoardContext $context, ImportCard $card): Card {
		$entity = new Card();
		$entity->setTitle($card->title);
		$entity->setDescription($card->description);
		$entity->setStackId($context->stackIds[$card->stackRef]);
		$entity->setType('plain');
		$entity->setOrder($card->order);
		$entity->setOwner($card->createdBy !== '' ? $card->createdBy : $context->board->getOwner());
		$entity->setStartdate(self::mutable($card->start));
		$entity->setDuedate(self::mutable($card->due));
		$entity->setDone(self::mutable($card->done));
		$entity->setArchived(false);
		$entity->setDeletedAt(0);
		$entity->setNotified($this->isPast($card->due));
		$entity = $this->cardMapper->insert($entity);

		// The mapper stamps the insert time, the import keeps the source's
		$entity->resetUpdatedFields();
		$entity->setCreatedAt($card->created->getTimestamp());
		$this->cardMapper->update($entity, false);
		return $entity;
	}

	private function updateCard(BoardContext $context, Card $entity, ImportCard $card): void {
		$entity->setTitle($card->title);
		$entity->setDescription($card->description);
		$entity->setStackId($context->stackIds[$card->stackRef]);
		$entity->setOrder($card->order);
		$entity->setOwner($card->createdBy !== '' ? $card->createdBy : $context->board->getOwner());
		$entity->setCreatedAt($card->created->getTimestamp());
		// Only differing times are set: the mapper resets the due notification when the due date is set
		if (self::time($entity->getStartdate()) !== self::time($card->start)) {
			$entity->setStartdate(self::mutable($card->start));
		}
		if (self::time($entity->getDuedate()) !== self::time($card->due)) {
			$entity->setDuedate(self::mutable($card->due));
		}
		if (self::time($entity->getDone()) !== self::time($card->done)) {
			$entity->setDone(self::mutable($card->done));
		}
		$this->cardMapper->update($entity);
		if ($this->isPast($card->due)) {
			$this->cardMapper->markNotified($entity);
		}
	}

	private function writeLabelsAndAssignees(BoardContext $context, int $cardId, ImportCard $card): void {
		$wantedLabels = array_map(fn (string $ref) => $context->labelIds[$ref], $card->labels);
		$currentLabels = array_map(fn (Label $label) => $label->getId(), $this->labelMapper->findAssignedLabelsForCard($cardId));
		foreach (array_diff($wantedLabels, $currentLabels) as $labelId) {
			$this->cardMapper->assignLabel($cardId, $labelId);
		}
		foreach (array_diff($currentLabels, $wantedLabels) as $labelId) {
			$this->cardMapper->removeLabel($cardId, $labelId);
		}

		$currentAssignments = [];
		foreach ($this->assignmentMapper->findAll($cardId) as $assignment) {
			$currentAssignments[self::assigneeKey($assignment)] = $assignment;
		}
		foreach ($card->assignees as $uid) {
			if (isset($currentAssignments[$uid])) {
				unset($currentAssignments[$uid]);
				continue;
			}
			$assignment = new Assignment();
			$assignment->setCardId($cardId);
			$assignment->setParticipant($uid);
			$assignment->setType(Assignment::TYPE_USER);
			$this->assignmentMapper->insert($assignment);
		}
		foreach ($currentAssignments as $assignment) {
			$this->assignmentMapper->delete($assignment);
		}
	}

	/**
	 * Finds each file and the user who shares it with a card: its owner,
	 * or the board owner for a file without one when they can see it
	 *
	 * @param list<int> $fileIds
	 * @return array<int, array{0: Node, 1: string}> the file in the sharer's view and the sharer, by file ID
	 * @throws EntryException
	 */
	private function resolveFiles(Board $board, array $fileIds): array {
		$files = [];
		foreach ($fileIds as $fileId) {
			$sharer = null;
			$node = null;
			try {
				$sharer = $this->rootFolder->getFirstNodeById($fileId)?->getOwner()?->getUID();
				$node = $sharer === null ? null : $this->rootFolder->getUserFolder($sharer)->getFirstNodeById($fileId);
				if ($node === null) {
					$sharer = $board->getOwner();
					$node = $this->rootFolder->getUserFolder($sharer)->getFirstNodeById($fileId);
				}
			} catch (\Exception) {
				$node = null;
			}
			if ($node === null || $sharer === null) {
				throw EntryException::error(ImportResult::ERROR_FILE_UNAVAILABLE, 'File ' . $fileId . ' was not found');
			}
			if (!$node->isShareable()) {
				throw EntryException::error(ImportResult::ERROR_FILE_UNAVAILABLE, 'File ' . $fileId . ' may not be shared');
			}
			$files[$fileId] = [$node, $sharer];
		}
		return $files;
	}

	/**
	 * Makes $fileIds the card's file attachments
	 *
	 * @param list<int> $fileIds
	 * @param array<int, array{0: Node, 1: string}> $resolved the files that weren't attached yet
	 * @throws EntryException
	 */
	private function writeFiles(Board $board, int $cardId, array $fileIds, array $resolved): void {
		$attached = $this->cardFiles($cardId);
		foreach ($fileIds as $fileId) {
			if (!isset($attached[$fileId])) {
				[$node, $sharer] = $resolved[$fileId] ?? $this->resolveFiles($board, [$fileId])[$fileId];
				$this->attachFile($board, $cardId, $fileId, $node, $sharer);
			}
		}
		foreach (array_diff_key($attached, array_flip($fileIds)) as $fileId => $shareId) {
			try {
				$this->shareManager->deleteShare($this->shareProvider->getShareById((string)$shareId));
			} catch (\Exception $e) {
				$this->logger->info('Imported file attachment could not be removed', ['exception' => $e, 'fileId' => $fileId, 'cardId' => $cardId]);
				throw EntryException::error(ImportResult::ERROR_FILE_UNAVAILABLE, 'File ' . $fileId . ' could not be detached');
			}
		}
	}

	/**
	 * Shares an existing file with the card, which makes it a Deck
	 * attachment of type `file`
	 *
	 * @throws EntryException
	 */
	private function attachFile(Board $board, int $cardId, int $fileId, Node $node, string $sharer): void {
		$share = $this->shareManager->newShare();
		$share->setNode($node);
		$share->setShareType(IShare::TYPE_DECK);
		$share->setSharedWith((string)$cardId);
		$share->setPermissions(Constants::PERMISSION_READ);
		$share->setSharedBy($sharer);
		$share->setMailSend(false);

		// The Deck share provider checks that its user may edit the card's board
		$previousUser = $this->permissionService->getUserId();
		$this->permissionService->setUserId($board->getOwner());
		try {
			$this->shareManager->createShare($share);
		} catch (\Exception $e) {
			$this->logger->info('Imported file attachment could not be shared', ['exception' => $e, 'fileId' => $fileId, 'cardId' => $cardId]);
			throw EntryException::error(ImportResult::ERROR_FILE_UNAVAILABLE, 'File ' . $fileId . ' could not be shared');
		} finally {
			$this->permissionService->setUserId($previousUser);
		}
	}

	/**
	 * The files attached to a card as Deck shares
	 *
	 * @return array<int, int> share IDs by file ID
	 */
	private function cardFiles(int $cardId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.id', 's.file_source', 'f.fileid', 'f.path')
			->selectAlias('st.id', 'storage_string_id')
			->from('share', 's')
			->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
			->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
			->where($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('s.share_with', $qb->createNamedParameter((string)$cardId)))
			->andWhere($qb->expr()->isNull('s.parent'))
			->andWhere($qb->expr()->in('s.item_type', $qb->createNamedParameter(['file', 'folder'], IQueryBuilder::PARAM_STR_ARRAY)));
		$files = [];
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			if ($this->shareProvider->isAccessibleResult($row)) {
				$files[(int)$row['file_source']] = (int)$row['id'];
			}
		}
		$result->closeCursor();
		return $files;
	}

	/**
	 * The SHA-256 of the card's canonical form as Deck holds it
	 */
	private function storedHash(BoardContext $context, string $ref, Card $card): string {
		$labelRefs = array_map(fn (Label $label) => $context->labelRef($label->getId()), $this->labelMapper->findAssignedLabelsForCard($card->getId()));
		$assignees = array_map(self::assigneeKey(...), $this->assignmentMapper->findAll($card->getId()));
		return CardCanonicalForm::hash(
			$ref,
			$context->stackRef($card->getStackId()),
			$card->getTitle(),
			(string)$card->getDescription(),
			(int)$card->getOrder(),
			$card->getStartdate(),
			$card->getDuedate(),
			$card->getDone(),
			array_values($assignees),
			array_values($labelRefs),
			array_keys($this->cardFiles($card->getId())),
		);
	}

	/**
	 * Whether somebody changed, archived or deleted a card since the importer wrote it
	 */
	private function changedInDeck(Card $card, string $currentHash, ImportRef $cardRef): bool {
		if ($card->getArchived() || $card->getDeletedAt() > 0) {
			return true;
		}
		// A write the importer didn't finish differs from its last hash by design
		return $cardRef->getSourceHash() !== null && $currentHash !== $cardRef->getStoredHash();
	}

	#[\Override]
	public function statCards(string $app, int $boardId, array $refs, bool $hash): array {
		$context = $this->context($app, $boardId);
		$found = $this->refMapper->findByRefs($app, ImportRef::KIND_CARD, $boardId, $refs);
		$stats = [];
		foreach ($refs as $ref) {
			$cardRef = $found[$ref] ?? null;
			if ($cardRef === null) {
				$stats[] = new ImportCardStat($ref, false);
				continue;
			}
			$card = $this->findCard($cardRef->getObjectId());
			$removed = $cardRef->getState() === ImportRef::STATE_REMOVED;
			if ($card === null) {
				$stats[] = new ImportCardStat($ref, false, null, null, !$removed);
				continue;
			}
			$current = $this->storedHash($context, $ref, $card);
			$changed = $removed
				? $card->getDeletedAt() === 0 || $current !== $cardRef->getStoredHash()
				: $this->changedInDeck($card, $current, $cardRef);
			$stats[] = new ImportCardStat($ref, !$removed, $card->getId(), $hash ? $current : null, $changed);
		}
		return $stats;
	}

	#[\Override]
	public function listCards(string $app, int $boardId, string $cursor, int $limit): array {
		$this->context($app, $boardId);
		if ($cursor !== '' && preg_match('/^[1-9][0-9]{0,18}$/', $cursor) !== 1) {
			throw new ImportException(ImportException::INVALID, 'The cursor is invalid');
		}
		if ($limit < 1 || $limit > self::MAX_LIST_LIMIT) {
			throw new ImportException(ImportException::INVALID, 'The limit must be between 1 and ' . self::MAX_LIST_LIMIT);
		}
		$page = $this->refMapper->findCardPage($app, $boardId, $cursor === '' ? 0 : (int)$cursor, $limit + 1);
		$next = '';
		if (count($page) > $limit) {
			array_pop($page);
			$next = (string)$page[$limit - 1]->getId();
		}
		return [
			'entries' => array_map(fn (ImportRef $ref) => $ref->getRef(), $page),
			'next_cursor' => $next,
		];
	}

	#[\Override]
	public function removeCards(string $app, int $boardId, array $refs): array {
		$context = $this->context($app, $boardId);
		$results = [];
		foreach ($refs as $ref) {
			try {
				[$result, $removed] = $this->atomic(fn () => $this->removeCard($app, $context, $ref), $this->db);
				if ($removed !== null) {
					$this->fullTextSearch->handle(new CardDeletedEvent($removed));
				}
				$results[] = $result;
			} catch (EntryException $e) {
				$results[] = $e->result;
			}
		}
		return $results;
	}

	/**
	 * @return array{0: ImportResult, 1: ?Card} the result and the card this call removed
	 * @throws EntryException
	 */
	private function removeCard(string $app, BoardContext $context, string $ref): array {
		$cardRef = $this->refMapper->findByRef($app, ImportRef::KIND_CARD, $context->board->getId(), $ref);
		if ($cardRef === null) {
			throw EntryException::error(ImportResult::ERROR_NOT_FOUND, 'The card was not imported');
		}
		if ($cardRef->getState() === ImportRef::STATE_REMOVED) {
			return [ImportResult::ok($cardRef->getObjectId()), null];
		}
		$card = $this->findCard($cardRef->getObjectId());
		if ($card === null) {
			throw EntryException::conflict(ImportResult::ERROR_CHANGED, 'The card was deleted in Deck', $cardRef->getObjectId());
		}
		$current = $this->storedHash($context, $ref, $card);
		if ($this->changedInDeck($card, $current, $cardRef)) {
			throw EntryException::conflict(ImportResult::ERROR_CHANGED, 'The card was changed in Deck', $card->getId(), $current);
		}

		$card->setDeletedAt($this->timeFactory->getTime());
		$this->cardMapper->update($card);
		$cardRef->setState(ImportRef::STATE_REMOVED);
		$this->updateRef($cardRef, $current);
		$this->changeHelper->cardChanged($card->getId(), false);
		return [ImportResult::ok($card->getId()), $card];
	}

	#[\Override]
	public function setAccess(string $app, int $boardId, array $acl): array {
		$context = $this->context($app, $boardId);
		$board = $context->board;
		$this->markPastDueNotified($app, $boardId);
		$managed = [];
		foreach ($this->refMapper->findAllOfKind($app, ImportRef::KIND_ACL, $boardId) as $aclRef) {
			$managed[$aclRef->getRef()] = $aclRef;
		}

		$results = [];
		$wanted = [];
		foreach ($acl as $access) {
			try {
				if (!$access instanceof ImportAccess) {
					throw EntryException::error(ImportResult::ERROR_INVALID, 'Not a share');
				}
				if (isset($wanted[$access->ref()])) {
					throw EntryException::error(ImportResult::ERROR_INVALID, 'The share appears twice');
				}
				$wanted[$access->ref()] = true;
				[$result, $change] = $this->atomic(fn () => $this->writeAccess($app, $board, $access, $managed[$access->ref()] ?? null), $this->db);
				if ($change !== null) {
					$this->accessChanged($change[0], $change[1]);
				}
				$results[] = $result;
			} catch (EntryException $e) {
				$results[] = $e->result;
			}
		}

		foreach ($managed as $ref => $aclRef) {
			if (!isset($wanted[$ref])) {
				$change = $this->atomic(fn () => $this->removeAccess($aclRef), $this->db);
				if ($change !== null) {
					$this->accessChanged($change[0], $change[1]);
				}
			}
		}

		$this->boardMapper->flushCache($boardId);
		$this->changeHelper->boardChanged($boardId);
		return $results;
	}

	/**
	 * @return array{0: ImportResult, 1: ?array{0: AclCreatedEvent|AclUpdatedEvent, 1: Operation}} the result and the change to announce
	 * @throws EntryException
	 */
	private function writeAccess(string $app, Board $board, ImportAccess $access, ?ImportRef $aclRef): array {
		if ($access->type === ImportAccess::TYPE_USER) {
			$type = Acl::PERMISSION_TYPE_USER;
			if (!$this->userManager->userExists($access->id)) {
				throw EntryException::error(ImportResult::ERROR_UNKNOWN_USER, 'The user does not exist');
			}
			if ($access->id === $board->getOwner()) {
				return [ImportResult::ok(), null];
			}
		} elseif ($access->type === ImportAccess::TYPE_GROUP) {
			$type = Acl::PERMISSION_TYPE_GROUP;
			if (!$this->groupManager->groupExists($access->id)) {
				throw EntryException::error(ImportResult::ERROR_UNKNOWN_GROUP, 'The group does not exist');
			}
		} else {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The type must be user or group');
		}

		$next = self::valuesHash($access->edit, $access->share, $access->manage);
		$existing = null;
		foreach ($this->aclMapper->findAll($board->getId()) as $acl) {
			if ($acl->getType() === $type && $acl->getParticipant() === $access->id) {
				$existing = $acl;
			}
		}
		if ($existing === null && $aclRef !== null) {
			throw EntryException::conflict(ImportResult::ERROR_CHANGED, 'The share was removed in Deck');
		}
		if ($existing === null) {
			$entity = new Acl();
			$entity->setBoardId($board->getId());
			$entity->setType($type);
			$entity->setParticipant($access->id);
			$entity->setPermissionEdit($access->edit);
			$entity->setPermissionShare($access->share);
			$entity->setPermissionManage($access->manage);
			$entity = $this->aclMapper->insert($entity);
			$this->insertRef($app, ImportRef::KIND_ACL, $board->getId(), $access->ref(), $entity->getId(), $next);
			return [ImportResult::ok(), [new AclCreatedEvent($entity), Operation::BoardShareCreated]];
		}

		if ($aclRef === null || $aclRef->getObjectId() !== $existing->getId()) {
			throw EntryException::conflict(ImportResult::ERROR_EXISTS, 'The board is already shared with them in Deck');
		}
		$current = self::valuesHash($existing->getPermissionEdit(), $existing->getPermissionShare(), $existing->getPermissionManage());
		if ($current !== $aclRef->getStoredHash()) {
			throw EntryException::conflict(ImportResult::ERROR_CHANGED, 'The share was changed in Deck');
		}
		if ($current !== $next) {
			$existing->setPermissionEdit($access->edit);
			$existing->setPermissionShare($access->share);
			$existing->setPermissionManage($access->manage);
			$this->aclMapper->update($existing);
			$this->updateRef($aclRef, $next);
			return [ImportResult::ok(), [new AclUpdatedEvent($existing), Operation::BoardShareUpdated]];
		}
		return [ImportResult::ok(), null];
	}

	/**
	 * Removes a share the importer made unless somebody changed it in Deck
	 *
	 * @return ?array{0: AclDeletedEvent, 1: Operation} the change to announce
	 */
	private function removeAccess(ImportRef $aclRef): ?array {
		try {
			/** @var Acl $existing */
			$existing = $this->aclMapper->find($aclRef->getObjectId());
		} catch (DoesNotExistException) {
			$this->refMapper->delete($aclRef);
			return null;
		}
		$current = self::valuesHash($existing->getPermissionEdit(), $existing->getPermissionShare(), $existing->getPermissionManage());
		if ($current !== $aclRef->getStoredHash()) {
			return null;
		}
		$this->aclMapper->delete($existing);
		$this->refMapper->delete($aclRef);
		return [new AclDeletedEvent($existing), Operation::BoardShareDeleted];
	}

	/**
	 * Updates share access, collaboration resources, full-text search and
	 * the audit log through Deck's own listeners, without dispatching the
	 * event: its live update listener would push to open sessions
	 */
	private function accessChanged(AclCreatedEvent|AclUpdatedEvent|AclDeletedEvent $event, Operation $operation): void {
		$this->shareAccess->handle($event);
		$this->resources->handle($event);
		$this->fullTextSearch->handle($event);

		$acl = $event->getAcl();
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
			'Share %s of board %s with %s "%s" was set by an import (permissions: %s)',
			[
				'aclId' => $acl->getId(),
				'boardId' => $acl->getBoardId(),
				'shareType' => $acl->getType() === Acl::PERMISSION_TYPE_USER ? 'user' : 'group',
				'sharedWith' => $acl->getParticipant(),
				'permissions' => implode(', ', $permissions),
			],
			false,
			$operation->value,
		));
	}

	/**
	 * Imported cards that were already due when they were shared don't
	 * announce themselves as overdue to the people who get the board
	 */
	private function markPastDueNotified(string $app, int $boardId): void {
		$cardIds = array_map(fn (ImportRef $ref) => $ref->getObjectId(), $this->refMapper->findAllOfKind($app, ImportRef::KIND_CARD, $boardId));
		foreach (array_chunk($cardIds, 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('deck_cards')
				->set('notified', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->lte('duedate', $qb->createNamedParameter($this->timeFactory->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
				->andWhere($qb->expr()->eq('notified', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
			$qb->executeStatement();
		}
	}

	/**
	 * The board with the imported stacks and labels that still exist
	 *
	 * @throws ImportException
	 */
	private function context(string $app, int $boardId): BoardContext {
		try {
			$this->checkApp($app);
		} catch (EntryException $e) {
			throw new ImportException(ImportException::INVALID, $e->getMessage());
		}
		if ($boardId < 1 || $boardId > self::MAX_DECK_ID) {
			throw new ImportException(ImportException::NOT_FOUND, 'The board does not exist');
		}
		$boardRef = $this->refMapper->findBoard($boardId);
		try {
			$board = $this->boardMapper->find($boardId);
		} catch (DoesNotExistException) {
			throw new ImportException(ImportException::NOT_FOUND, 'The board does not exist');
		}
		if ($boardRef === null || $boardRef->getApp() !== $app) {
			throw new ImportException(ImportException::NOT_IMPORTER, 'The board was not imported by ' . $app);
		}

		$stackIds = [];
		$stacks = [];
		foreach ($this->stackMapper->findAll($boardId) as $stack) {
			$stacks[$stack->getId()] = true;
		}
		foreach ($this->refMapper->findAllOfKind($app, ImportRef::KIND_STACK, $boardId) as $stackRef) {
			if (isset($stacks[$stackRef->getObjectId()])) {
				$stackIds[$stackRef->getRef()] = $stackRef->getObjectId();
			}
		}
		$labelIds = [];
		$labels = [];
		foreach ($this->labelMapper->findAll($boardId) as $label) {
			$labels[$label->getId()] = true;
		}
		foreach ($this->refMapper->findAllOfKind($app, ImportRef::KIND_LABEL, $boardId) as $labelRef) {
			if (isset($labels[$labelRef->getObjectId()])) {
				$labelIds[$labelRef->getRef()] = $labelRef->getObjectId();
			}
		}
		return new BoardContext($board, $stackIds, $labelIds);
	}

	private function findCard(int $cardId): ?Card {
		try {
			return $this->cardMapper->find($cardId, false);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	private function insertRef(string $app, string $kind, int $boardId, string $ref, int $objectId, ?string $storedHash): ImportRef {
		$now = $this->timeFactory->getTime();
		$importRef = new ImportRef();
		$importRef->setApp($app);
		$importRef->setKind($kind);
		$importRef->setBoardId($boardId);
		$importRef->setRef($ref);
		$importRef->setRefHash(ImportRef::hashRef($ref));
		$importRef->setObjectId($objectId);
		$importRef->setStoredHash($storedHash);
		$importRef->setState(ImportRef::STATE_ACTIVE);
		$importRef->setCreatedAt($now);
		$importRef->setUpdatedAt($now);
		return $this->refMapper->insert($importRef);
	}

	private function updateRef(ImportRef $importRef, string $storedHash, ?string $sourceHash = null): void {
		$importRef->setStoredHash($storedHash);
		if ($sourceHash !== null) {
			$importRef->setSourceHash($sourceHash);
		}
		$importRef->setUpdatedAt($this->timeFactory->getTime());
		$this->refMapper->update($importRef);
	}

	/**
	 * Runs a write once more when a concurrent import of the same reference
	 * won the race; the second run finds its rows
	 *
	 * @template T
	 * @param callable(): T $write
	 * @return T
	 */
	private function retryOnUniqueViolation(callable $write): mixed {
		try {
			return $write();
		} catch (DbException $e) {
			if ($e->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			return $write();
		}
	}

	/**
	 * @throws EntryException
	 */
	private function checkApp(string $app): void {
		if (preg_match('/^[a-z0-9_]{1,32}$/', $app) !== 1) {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The app must match [a-z0-9_]{1,32}');
		}
	}

	/**
	 * @throws EntryException
	 */
	private function checkRef(string $field, string $ref): void {
		if ($ref === '' || strlen($ref) > self::MAX_REF_BYTES) {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The ' . $field . ' must have 1 to ' . self::MAX_REF_BYTES . ' bytes');
		}
	}

	/**
	 * @throws EntryException
	 */
	private function checkTitle(string $field, string $title, int $max): void {
		$length = mb_strlen($title, 'UTF-8');
		if ($length === 0 || $length > $max) {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The ' . $field . ' must have 1 to ' . $max . ' characters');
		}
	}

	/**
	 * @throws EntryException
	 */
	private function checkColor(string $field, string $color): void {
		if (preg_match('/^[0-9a-fA-F]{6}$/', $color) !== 1) {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The ' . $field . ' must be rrggbb');
		}
	}

	/**
	 * Lists of distinct values that each pass $valid
	 *
	 * @throws EntryException
	 */
	private function checkList(string $field, array $values, callable $valid): void {
		if (!array_is_list($values) || count($values) !== count(array_unique($values, SORT_REGULAR))) {
			throw EntryException::error(ImportResult::ERROR_INVALID, 'The ' . $field . ' must be a list of distinct values');
		}
		foreach ($values as $value) {
			if (!$valid($value)) {
				throw EntryException::error(ImportResult::ERROR_INVALID, 'The ' . $field . ' hold an invalid value');
			}
		}
	}

	/**
	 * @throws EntryException
	 */
	private function checkLabels(array $labels): void {
		$refs = [];
		$titles = [];
		foreach ($labels as $label) {
			if (!$label instanceof ImportLabel) {
				throw EntryException::error(ImportResult::ERROR_INVALID, 'The labels must be ImportLabel objects');
			}
			$this->checkRef('label ref', $label->ref);
			$this->checkTitle('label title', $label->title, self::MAX_LABEL_TITLE);
			$this->checkColor('label color', $label->color);
			if (isset($refs[$label->ref]) || isset($titles[$label->title])) {
				throw EntryException::error(ImportResult::ERROR_INVALID, 'Label refs and titles must be distinct');
			}
			$refs[$label->ref] = true;
			$titles[$label->title] = true;
		}
	}

	/**
	 * @throws EntryException
	 */
	private function checkStacks(array $stacks): void {
		$refs = [];
		foreach ($stacks as $stack) {
			if (!$stack instanceof ImportStack) {
				throw EntryException::error(ImportResult::ERROR_INVALID, 'The stacks must be ImportStack objects');
			}
			$this->checkRef('stack ref', $stack->ref);
			$this->checkTitle('stack title', $stack->title, self::MAX_STACK_TITLE);
			if (isset($refs[$stack->ref])) {
				throw EntryException::error(ImportResult::ERROR_INVALID, 'Stack refs must be distinct');
			}
			$refs[$stack->ref] = true;
		}
	}

	private function isPast(?\DateTimeImmutable $time): bool {
		return $time !== null && $time <= $this->timeFactory->now();
	}

	private static function assigneeKey(Assignment $assignment): string {
		return $assignment->getType() === Assignment::TYPE_USER
			? $assignment->getParticipant()
			: $assignment->getType() . ':' . $assignment->getParticipant();
	}

	/**
	 * Fingerprint of the values the importer last wrote to a board, label,
	 * stack or share, to tell whether somebody changed them since
	 */
	private static function valuesHash(string|int|bool ...$values): string {
		return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
	}

	private static function time(?\DateTimeInterface $time): ?string {
		return CardCanonicalForm::time($time);
	}

	private static function mutable(?\DateTimeImmutable $time): ?\DateTime {
		return $time === null ? null : \DateTime::createFromImmutable($time->setTimezone(new \DateTimeZone('UTC')));
	}
}
