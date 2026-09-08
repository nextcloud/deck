<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Model\CardDetails;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use Psr\Log\LoggerInterface;

/**
 * Builds a complete, self-contained representation of a board.
 *
 * This is the single source of truth for every export path (the web UI, the
 * `occ deck:export` command and the user migrator), so that all of them produce
 * data that can be fed back into the DeckJson importer without losing state.
 *
 * Everything a board consists of is included: archived cards, the completion
 * state and every date field of a card, comments and attachment contents.
 */
class BoardExportService {
	public function __construct(
		private BoardMapper $boardMapper,
		private StackMapper $stackMapper,
		private CardMapper $cardMapper,
		private LabelMapper $labelMapper,
		private AssignmentMapper $assignmentMapper,
		private AttachmentMapper $attachmentMapper,
		private ICommentsManager $commentsManager,
		private ShareFileAttachmentExportService $shareFileAttachmentExportService,
		private FileService $fileService,
		private PermissionService $permissionService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Set a different user than the current one, e.g. when no user is available in occ
	 */
	public function setUserId(string $userId): void {
		$this->permissionService->setUserId($userId);
	}

	/**
	 * Export a single board including everything needed to restore it.
	 *
	 * @return array<string, mixed>
	 * @throws \OCA\Deck\NoPermissionException
	 */
	public function exportBoard(int $boardId, BoardExportOptions $options = new BoardExportOptions()): array {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);

		$board = $this->boardMapper->find($boardId, true, true);
		$this->boardMapper->mapOwner($board);
		foreach ($board->getAcl() ?? [] as &$acl) {
			$this->boardMapper->mapAcl($acl);
		}

		return $this->serializeBoard($board, $options);
	}

	/**
	 * Export a set of boards, keyed by board id. Boards in the trash are
	 * skipped, they are not part of the state a user wants to restore.
	 *
	 * @param Board[] $boards
	 * @return array<int, array<string, mixed>>
	 */
	public function exportBoards(array $boards, BoardExportOptions $options = new BoardExportOptions()): array {
		$exported = [];
		foreach ($boards as $board) {
			if ($board->getDeletedAt() > 0) {
				continue;
			}
			$exported[$board->getId()] = $this->exportBoard($board->getId(), $options);
		}

		return $exported;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeBoard(Board $board, BoardExportOptions $options): array {
		$data = $board->jsonSerialize();
		// Permissions describe the requesting user, not the board itself
		unset($data['permissions'], $data['activeSessions']);

		$stacks = $this->stackMapper->findAll($board->getId());
		$data['stacks'] = $this->serializeStacks($board, $stacks, $options);

		return $data;
	}

	/**
	 * @param Stack[] $stacks
	 * @return list<array<string, mixed>>
	 */
	private function serializeStacks(Board $board, array $stacks, BoardExportOptions $options): array {
		if (count($stacks) === 0) {
			return [];
		}

		$stackIds = array_map(static fn (Stack $stack) => $stack->getId(), $stacks);
		$cardsByStack = $this->collectCards($stackIds, $options);

		$allCards = array_merge(...array_values($cardsByStack)) ?: [];
		$cardIds = array_map(static fn (Card $card) => $card->getId(), $allCards);
		$labelsByCard = $this->collectLabels($cardIds);
		$assignmentsByCard = $this->collectAssignments($cardIds);
		// The attachment count is reported even when the contents are left out,
		// otherwise a CSV export would show every card as having none
		$attachmentsByCard = $options->includeAttachments ? $this->collectAttachments($cardIds) : [];
		$attachmentCounts = $options->includeAttachments
			? array_map(static fn (array $attachments) => count($attachments), $attachmentsByCard)
			: $this->collectAttachmentCounts($cardIds);
		$dependenciesByCard = $this->cardMapper->findDependenciesForCards($cardIds);

		$serialized = [];
		foreach ($stacks as $stack) {
			$stackData = $stack->jsonSerialize();
			$stackData['cards'] = array_map(
				fn (Card $card) => $this->serializeCard(
					$card,
					$board,
					$labelsByCard[$card->getId()] ?? [],
					$assignmentsByCard[$card->getId()] ?? [],
					$attachmentsByCard[$card->getId()] ?? [],
					$attachmentCounts[$card->getId()] ?? 0,
					$dependenciesByCard[$card->getId()] ?? [],
					$options,
				),
				$cardsByStack[$stack->getId()] ?? [],
			);
			$serialized[] = $stackData;
		}

		return $serialized;
	}

	/**
	 * @param int[] $stackIds
	 * @return array<int, list<Card>>
	 */
	private function collectCards(array $stackIds, BoardExportOptions $options): array {
		$cardsByStack = array_fill_keys($stackIds, []);

		foreach ($this->cardMapper->findAllForStacks($stackIds) as $stackId => $cards) {
			$cardsByStack[$stackId] = $cards ?? [];
		}

		if (!$options->includeArchivedCards) {
			return $cardsByStack;
		}

		foreach ($this->cardMapper->findAllArchivedForStacks($stackIds) as $stackId => $cards) {
			if (count($cards) > 0) {
				$cardsByStack[$stackId] = array_merge($cardsByStack[$stackId] ?? [], $cards);
			}
		}

		return $cardsByStack;
	}

	/**
	 * @param int[] $cardIds
	 * @return array<int, list<Label>>
	 */
	private function collectLabels(array $cardIds): array {
		if (count($cardIds) === 0) {
			return [];
		}

		$labelsByCard = [];
		foreach ($this->labelMapper->findAssignedLabelsForCards($cardIds) as $label) {
			$labelsByCard[$label->getCardId()][] = $label;
		}

		return $labelsByCard;
	}

	/**
	 * @param int[] $cardIds
	 * @return array<int, list<Assignment>>
	 */
	private function collectAssignments(array $cardIds): array {
		if (count($cardIds) === 0) {
			return [];
		}

		$assignmentsByCard = [];
		foreach ($this->assignmentMapper->findIn($cardIds) as $assignment) {
			$assignmentsByCard[$assignment->getCardId()][] = $assignment;
		}

		return $assignmentsByCard;
	}

	/**
	 * @param Label[] $labels
	 * @param Assignment[] $assignments
	 * @param list<array<string, mixed>> $attachments
	 * @param int $attachmentCount how many the card has, which can be more than
	 *                             the number of exported payloads
	 * @param int[] $dependentCards ids of the cards this one depends on
	 * @return array<string, mixed>
	 */
	private function serializeCard(Card $card, Board $board, array $labels, array $assignments, array $attachments, int $attachmentCount, array $dependentCards, BoardExportOptions $options): array {
		$card->setLabels($labels);
		$card->setAssignedUsers($assignments);
		$card->setDependentCards($dependentCards);

		$comments = $options->includeComments ? $this->serializeComments($card->getId()) : [];
		$card->setCommentsCount(count($comments));
		$card->setAttachmentCount($attachmentCount);

		$data = (new CardDetails($card, $board))->jsonSerialize();
		$data['comments'] = $comments;
		$data['attachments'] = $attachments;

		return $data;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function serializeComments(int $cardId): array {
		$comments = iterator_to_array($this->commentsManager->getForObject(
			Application::COMMENT_ENTITY_TYPE,
			(string)$cardId,
		));
		usort($comments, static fn (IComment $first, IComment $second) => ((int)$first->getId()) <=> ((int)$second->getId()));

		return array_map(static fn (IComment $comment) => [
			'id' => $comment->getId(),
			'parentId' => $comment->getParentId(),
			'actorType' => $comment->getActorType(),
			'actorId' => $comment->getActorId(),
			'message' => $comment->getMessage(),
			'creationDateTime' => $comment->getCreationDateTime()->format(\DateTime::ATOM),
			'objectType' => $comment->getObjectType(),
			'objectId' => $comment->getObjectId(),
			'verb' => $comment->getVerb(),
		], $comments);
	}

	/**
	 * Attachments live in two places: `deck_file` attachments are stored in the
	 * app data folder, `file` attachments are shares of a Files app node. Both
	 * are exported with their content so an import can recreate them.
	 *
	 * @param int[] $cardIds
	 * @return array<int, list<array<string, mixed>>>
	 */
	private function collectAttachments(array $cardIds): array {
		if (count($cardIds) === 0) {
			return [];
		}

		$attachmentsByCard = [];
		foreach ($this->collectDeckFileAttachments($cardIds) as $cardId => $attachments) {
			foreach ($attachments as $attachment) {
				$serialized = $this->serializeDeckFileAttachment($attachment);
				if ($serialized !== null) {
					$attachmentsByCard[$cardId][] = $serialized;
				}
			}
		}

		$shared = $this->shareFileAttachmentExportService->exportAttachmentsForCards(
			$cardIds,
			$this->permissionService->getUserId() ?? '',
		);
		foreach ($shared as $cardId => $attachments) {
			$attachmentsByCard[$cardId] = array_merge($attachmentsByCard[$cardId] ?? [], $attachments);
		}

		return $attachmentsByCard;
	}

	/**
	 * How many attachments each card has, without reading a single file.
	 *
	 * @param int[] $cardIds
	 * @return array<int, int>
	 */
	private function collectAttachmentCounts(array $cardIds): array {
		if (count($cardIds) === 0) {
			return [];
		}

		$counts = [];
		foreach ($this->collectDeckFileAttachments($cardIds) as $cardId => $attachments) {
			$counts[$cardId] = count($attachments);
		}
		foreach ($this->shareFileAttachmentExportService->countAttachmentsForCards($cardIds) as $cardId => $count) {
			$counts[$cardId] = ($counts[$cardId] ?? 0) + $count;
		}

		return $counts;
	}

	/**
	 * @param int[] $cardIds
	 * @return array<int, list<Attachment>>
	 */
	private function collectDeckFileAttachments(array $cardIds): array {
		$byCard = [];
		foreach ($this->attachmentMapper->findAllForCards($cardIds) as $cardId => $attachments) {
			foreach ($attachments as $attachment) {
				// `file` attachments are Files app shares, they have no row here
				if ($attachment instanceof Attachment && $attachment->getType() === 'deck_file') {
					$byCard[$cardId][] = $attachment;
				}
			}
		}

		return $byCard;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function serializeDeckFileAttachment(Attachment $attachment): ?array {
		try {
			$content = $this->fileService->getFolder($attachment)
				->getFile($attachment->getData())
				->getContent();
		} catch (\Throwable $e) {
			$this->logger->info('Could not read attachment content for export', ['exception' => $e]);
			return null;
		}

		return [
			'type' => 'file',
			'data' => (string)$attachment->getData(),
			'createdBy' => (string)$attachment->getCreatedBy(),
			'createdAt' => (int)$attachment->getCreatedAt(),
			'lastModified' => (int)$attachment->getLastModified(),
			'contentBase64' => base64_encode($content),
		];
	}
}
