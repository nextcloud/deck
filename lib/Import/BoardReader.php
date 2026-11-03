<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\ICommentsManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IShare;

/**
 * {@see IBoardReader} on Deck's tables, the comments and the shares
 */
class BoardReader implements IBoardReader {
	/** The object type of card comments in the comments table */
	private const COMMENT_OBJECT = 'deckCard';
	private const ASSIGNEE_TYPES = [
		Assignment::TYPE_USER => self::ASSIGNEE_USER,
		Assignment::TYPE_GROUP => self::ASSIGNEE_GROUP,
		Assignment::TYPE_CIRCLE => self::ASSIGNEE_CIRCLE,
	];

	public function __construct(
		private IDBConnection $db,
		private BoardMapper $boardMapper,
		private StackMapper $stackMapper,
		private LabelMapper $labelMapper,
		private ICommentsManager $commentsManager,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IRootFolder $rootFolder,
		private IConfig $config,
	) {
	}

	#[\Override]
	public function boards(string $userId): array {
		if (!$this->userManager->userExists($userId)) {
			return [];
		}
		$ids = $this->boardMapper->findBoardIds($userId);
		sort($ids);
		$counts = $this->cardCounts($ids);
		$boards = [];
		foreach ($ids as $id) {
			$board = $this->readable($id);
			if ($board === null) {
				continue;
			}
			$boards[] = [
				'id' => $board->getId(),
				'title' => $board->getTitle(),
				'owner' => $board->getOwner(),
				'archived' => (bool)$board->getArchived(),
				'cards' => $counts[$board->getId()] ?? 0,
				'last_modified' => (int)$board->getLastModified(),
			];
		}
		return $boards;
	}

	#[\Override]
	public function board(string $userId, int $boardId): array {
		$board = $boardId > 0 && $this->userManager->userExists($userId) && in_array($boardId, $this->boardMapper->findBoardIds($userId), true)
			? $this->readable($boardId)
			: null;
		if ($board === null) {
			throw new ImportException(ImportException::NOT_FOUND, 'The person has no board ' . $boardId);
		}

		$labels = [];
		foreach ($this->labelMapper->findAll($boardId) as $label) {
			$labels[] = ['id' => (int)$label->getId(), 'title' => (string)$label->getTitle(), 'color' => (string)$label->getColor()];
		}
		usort($labels, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

		$stacks = $this->stackMapper->findAll($boardId);
		usort($stacks, static fn ($a, $b): int => [(int)$a->getOrder(), (int)$a->getId()] <=> [(int)$b->getOrder(), (int)$b->getId()]);
		$cardsByStack = $this->cards(array_map(static fn ($stack): int => (int)$stack->getId(), $stacks));

		$out = [];
		foreach ($stacks as $stack) {
			$out[] = [
				'id' => (int)$stack->getId(),
				'title' => (string)$stack->getTitle(),
				'order' => (int)$stack->getOrder(),
				'cards' => $cardsByStack[(int)$stack->getId()] ?? [],
			];
		}

		return [
			'id' => $board->getId(),
			'title' => $board->getTitle(),
			'color' => (string)$board->getColor(),
			'owner' => $board->getOwner(),
			'archived' => (bool)$board->getArchived(),
			'labels' => $labels,
			'stacks' => $out,
		];
	}

	/**
	 * The board, unless it is deleted or of another server
	 */
	private function readable(int $boardId): ?Board {
		try {
			$board = $this->boardMapper->find($boardId);
		} catch (DoesNotExistException) {
			return null;
		}
		if ($board->getDeletedAt() > 0 || $board->getExternalId() !== null) {
			return null;
		}
		return $board;
	}

	/**
	 * Cards of the boards that are not deleted, in stacks that are not deleted
	 *
	 * @param list<int> $boardIds
	 * @return array<int, int> by board ID
	 */
	private function cardCounts(array $boardIds): array {
		$counts = [];
		foreach (array_chunk($boardIds, 1000) as $chunk) {
			$query = $this->db->getQueryBuilder();
			$query->select('s.board_id')
				->selectAlias($query->func()->count('c.id'), 'cards')
				->from('deck_cards', 'c')
				->innerJoin('c', 'deck_stacks', 's', $query->expr()->eq('s.id', 'c.stack_id'))
				->where($query->expr()->in('s.board_id', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($query->expr()->eq('s.deleted_at', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->eq('c.deleted_at', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
				->groupBy('s.board_id');
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$counts[(int)$row['board_id']] = (int)$row['cards'];
			}
			$result->closeCursor();
		}
		return $counts;
	}

	/**
	 * The cards of the stacks that are not deleted, archived ones too, in
	 * Deck's order, with everything on them
	 *
	 * @param list<int> $stackIds
	 * @return array<int, list<array{id: int, title: string, description: string, order: int, created: int, modified: int, due: ?int, done: ?int, archived: bool, labels: list<int>, assignees: list<array{type: string, id: string, display_name: string}>, attachments: list<array{type: string, file_id: int, name: string, size: int}>, comments: list<array{actor_type: string, actor_id: string, display_name: string, created: int, text: string}>}>> by stack ID
	 */
	private function cards(array $stackIds): array {
		if ($stackIds === []) {
			return [];
		}
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('deck_cards')
			->where($query->expr()->in('stack_id', $query->createNamedParameter($stackIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($query->expr()->eq('deleted_at', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->orderBy('stack_id')
			->addOrderBy('order')
			->addOrderBy('id');
		$result = $query->executeQuery();
		/** @var list<Card> $cards */
		$cards = [];
		while ($row = $result->fetchAssociative()) {
			$cards[] = Card::fromRow($row);
		}
		$result->closeCursor();

		$cardIds = array_map(static fn (Card $card): int => (int)$card->getId(), $cards);
		$labels = $this->cardLabels($cardIds);
		$assignees = $this->assignees($cardIds);
		$files = $this->sharedFiles($cardIds);
		$deckFiles = $this->deckFiles($cardIds);

		$out = [];
		foreach ($cards as $card) {
			$id = (int)$card->getId();
			$due = $card->getDuedate();
			$done = $card->getDone();
			$out[(int)$card->getStackId()][] = [
				'id' => $id,
				'title' => (string)$card->getTitle(),
				'description' => (string)$card->getDescription(),
				'order' => (int)$card->getOrder(),
				'created' => (int)$card->getCreatedAt(),
				'modified' => (int)$card->getLastModified(),
				'due' => $due instanceof \DateTimeInterface ? $due->getTimestamp() : null,
				'done' => $done instanceof \DateTimeInterface ? $done->getTimestamp() : null,
				'archived' => (bool)$card->getArchived(),
				'labels' => $labels[$id] ?? [],
				'assignees' => $assignees[$id] ?? [],
				'attachments' => array_merge($files[$id] ?? [], $deckFiles[$id] ?? []),
				'comments' => $this->comments($id),
			];
		}
		return $out;
	}

	/**
	 * @param list<int> $cardIds
	 * @return array<int, list<int>> label IDs by card ID
	 */
	private function cardLabels(array $cardIds): array {
		$out = [];
		foreach (array_chunk($cardIds, 1000) as $chunk) {
			$query = $this->db->getQueryBuilder();
			$query->select('card_id', 'label_id')
				->from('deck_assigned_labels')
				->where($query->expr()->in('card_id', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->orderBy('card_id')
				->addOrderBy('label_id');
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$out[(int)$row['card_id']][] = (int)$row['label_id'];
			}
			$result->closeCursor();
		}
		return $out;
	}

	/**
	 * @param list<int> $cardIds
	 * @return array<int, list<array{type: string, id: string, display_name: string}>> by card ID
	 */
	private function assignees(array $cardIds): array {
		$out = [];
		foreach (array_chunk($cardIds, 1000) as $chunk) {
			$query = $this->db->getQueryBuilder();
			$query->select('card_id', 'participant', 'type')
				->from('deck_assigned_users')
				->where($query->expr()->in('card_id', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->orderBy('card_id')
				->addOrderBy('id');
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$type = self::ASSIGNEE_TYPES[(int)$row['type']] ?? null;
				if ($type === null) {
					continue;
				}
				$participant = (string)$row['participant'];
				$out[(int)$row['card_id']][] = ['type' => $type, 'id' => $participant, 'display_name' => $this->displayName($type, $participant)];
			}
			$result->closeCursor();
		}
		return $out;
	}

	private function displayName(string $type, string $id): string {
		$name = match ($type) {
			self::ASSIGNEE_USER => $this->userManager->getDisplayName($id),
			self::ASSIGNEE_GROUP => $this->groupManager->getDisplayName($id),
			default => null,
		};
		return $name ?? $id;
	}

	/**
	 * Files shared to the cards, described from the file cache; shares
	 * whose file is gone or that were removed (no permissions) are left out
	 *
	 * @param list<int> $cardIds
	 * @return array<int, list<array{type: string, file_id: int, name: string, size: int}>> by card ID
	 */
	private function sharedFiles(array $cardIds): array {
		$out = [];
		foreach (array_chunk($cardIds, 1000) as $chunk) {
			$query = $this->db->getQueryBuilder();
			$query->select('s.share_with', 'f.fileid', 'f.name', 'f.size')
				->from('share', 's')
				->innerJoin('s', 'filecache', 'f', $query->expr()->eq('s.file_source', 'f.fileid'))
				->where($query->expr()->eq('s.share_type', $query->createNamedParameter(IShare::TYPE_DECK, IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->in('s.share_with', $query->createNamedParameter(array_map('strval', $chunk), IQueryBuilder::PARAM_STR_ARRAY)))
				->andWhere($query->expr()->isNull('s.parent'))
				->andWhere($query->expr()->gt('s.permissions', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->in('s.item_type', $query->createNamedParameter(['file', 'folder'], IQueryBuilder::PARAM_STR_ARRAY)))
				->orderBy('s.id');
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$out[(int)$row['share_with']][] = [
					'type' => self::ATTACHMENT_FILE,
					'file_id' => (int)$row['fileid'],
					'name' => (string)$row['name'],
					'size' => max(0, (int)$row['size']),
				];
			}
			$result->closeCursor();
		}
		return $out;
	}

	/**
	 * Attachments in Deck's own storage, with their size from the file cache
	 *
	 * @param list<int> $cardIds
	 * @return array<int, list<array{type: string, file_id: int, name: string, size: int}>> by card ID
	 */
	private function deckFiles(array $cardIds): array {
		$rows = [];
		foreach (array_chunk($cardIds, 1000) as $chunk) {
			$query = $this->db->getQueryBuilder();
			$query->select('card_id', 'data')
				->from('deck_attachment')
				->where($query->expr()->in('card_id', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($query->expr()->eq('deleted_at', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->eq('type', $query->createNamedParameter(self::ATTACHMENT_DECK_FILE)))
				->orderBy('id');
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$rows[] = [(int)$row['card_id'], (string)$row['data']];
			}
			$result->closeCursor();
		}
		if ($rows === []) {
			return [];
		}

		$storage = $this->rootFolder->getMountPoint()->getNumericStorageId();
		$appData = 'appdata_' . $this->config->getSystemValueString('instanceid') . '/deck/';
		$out = [];
		foreach ($rows as [$cardId, $name]) {
			$file = ['fileid' => 0, 'size' => 0];
			if ($storage !== null) {
				$query = $this->db->getQueryBuilder();
				$query->select('fileid', 'size')
					->from('filecache')
					->where($query->expr()->eq('storage', $query->createNamedParameter($storage, IQueryBuilder::PARAM_INT)))
					->andWhere($query->expr()->eq('path_hash', $query->createNamedParameter(md5($appData . 'file-card-' . $cardId . '/' . $name))));
				$result = $query->executeQuery();
				$file = $result->fetchAssociative() ?: $file;
				$result->closeCursor();
			}
			$out[$cardId][] = [
				'type' => self::ATTACHMENT_DECK_FILE,
				'file_id' => (int)$file['fileid'],
				'name' => $name,
				'size' => max(0, (int)$file['size']),
			];
		}
		return $out;
	}

	/**
	 * A card's comments, oldest first
	 *
	 * @return list<array{actor_type: string, actor_id: string, display_name: string, created: int, text: string}>
	 */
	private function comments(int $cardId): array {
		$comments = $this->commentsManager->getForObject(self::COMMENT_OBJECT, (string)$cardId);
		$out = [];
		foreach (array_reverse($comments) as $comment) {
			$actorType = $comment->getActorType();
			$actorId = $comment->getActorId();
			$out[] = [
				'actor_type' => $actorType,
				'actor_id' => $actorId,
				'display_name' => $actorType === 'users' ? ($this->userManager->getDisplayName($actorId) ?? $actorId) : $actorId,
				'created' => $comment->getCreationDateTime()->getTimestamp(),
				'text' => $comment->getMessage(),
			];
		}
		return $out;
	}
}
