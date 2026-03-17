<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\UserMigration;

use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Errors\InternalError;
use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\CommentService;
use OCA\Deck\Service\FilesAppService;
use OCA\Deck\Service\LabelService;
use OCA\Deck\Service\StackService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\ISizeEstimationMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Psr\Log\LoggerInterface;

use Symfony\Component\Console\Output\OutputInterface;

class DeckMigrator implements IMigrator, ISizeEstimationMigrator {

	use TMigratorBasicVersionHandling;

	protected const BOARDS = 'boards.json';
	protected const BOARD_ACLS = 'board_acl.json';
	protected const BOARD_LABELS = 'board_labels.json';
	protected const BOARD_STACKS = 'board_stacks.json';
	protected const STACK_CARDS = 'stack_cards.json';
	protected const CARD_ASSIGNED = 'card_assigned.json';
	protected const CARD_LABELS = 'card_labels.json';
	protected const CARD_ATTACHMENTS = 'card_attachments.json';
	protected const CARD_COMMENTS = 'card_comments.json';
	protected const JSON_DEPTH = 512;
	protected const JSON_OPTIONS = JSON_THROW_ON_ERROR;

	public function __construct(
		protected IL10N $l10n,
		protected IRootFolder $rootFolder,
		protected BoardService $boardService,
		protected BoardMapper $boardMapper,
		protected AclMapper $aclMapper,
		protected LabelMapper $labelMapper,
		protected StackMapper $stackMapper,
		protected CardMapper $cardMapper,
		protected AssignmentMapper $assignmentMapper,
		protected CommentService $commentService,
		protected FilesAppService $filesAppService,
		protected IConfig $config,
		protected StackService $stackService,
		protected LabelService $labelService,
		protected CardService $cardService,
		protected AssignmentService $assignmentService,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEstimatedExportSize(IUser $user): int|float {
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function export(
		IUser $user,
		IExportDestination $exportDestination,
		OutputInterface $output,
	): void {
		$userId = $user->getUID();

		try {
			$boards = $this->boardMapper->findAllByUser($userId);
			$exportDestination->addFileContents(self::BOARDS, json_encode($boards));

			$boardAcls = [];
			$boardLabels = [];
			$boardStacks = [];
			$stackCards = [];
			$cardComments = [];
			$cardAttachments = [];
			foreach ($boards as $board) {
				$acls = $this->aclMapper->findAll($board->getId());
				foreach ($acls as $acl) {
					$boardAcls[] = $acl;
				}

				$labels = $this->labelMapper->findAll($board->getId());
				$boardLabels = array_merge($boardLabels, $labels);

			[$newStacks, $newCards, $newComments] = $this->exportStacksCardsCommentsSimple($board->getId());
				$boardStacks = array_merge($boardStacks, $newStacks);
				$stackCards = array_merge($stackCards, $newCards);
				$cardComments = array_merge($cardComments, $newComments);
			}

			$exportDestination->addFileContents(self::BOARD_ACLS, json_encode($boardAcls));
			$exportDestination->addFileContents(self::BOARD_LABELS, json_encode($boardLabels));
			$exportDestination->addFileContents(self::BOARD_STACKS, json_encode($boardStacks));
			$exportDestination->addFileContents(self::STACK_CARDS, json_encode($stackCards));
			usort($cardComments, function ($firstComment, $secondComment) {
				return ($firstComment['id'] ?? 0) <=> ($secondComment['id'] ?? 0);
			});
			$exportDestination->addFileContents(self::CARD_COMMENTS, json_encode($cardComments));

			$cardIds = array_map(fn ($c) => $c->getId(), $stackCards);
			$cardAssignments = $this->assignmentMapper->findIn($cardIds);
			$exportDestination->addFileContents(self::CARD_ASSIGNED, json_encode($cardAssignments));

			$cardLabels = $this->labelMapper->findAssignedLabelsForCards($cardIds);
			$exportDestination->addFileContents(self::CARD_LABELS, json_encode($cardLabels));

			$cardAttachments = $this->filesAppService->getAllDeckSharesForCards($cardIds);
			$exportDestination->addFileContents(self::CARD_ATTACHMENTS, json_encode($cardAttachments));

			$this->exportCardAttachmentsFiles($cardAttachments, $userId, $exportDestination);

			$output->writeln('Export completed.');
		} catch (\Throwable $e) {
			throw new InternalError('Deck export error: ' . $e->getMessage());
		}

	}

	/**
	 * @param array $cardAttachments
	 * @param string $userId
	 * @param IExportDestination $exportDestination
	 *
	 * @return void
	 */
	private function exportCardAttachmentsFiles(array $cardAttachments, string $userId, IExportDestination $exportDestination): void {
		foreach ($cardAttachments as $share) {
			if (!empty($share['file_target'])) {
				$fileTarget = $share['file_target'];
				$fileTargetClean = str_replace('{DECK_PLACEHOLDER}/', '', ltrim($fileTarget, '/'));
				if (strpos($fileTargetClean, 'Deck/') !== 0) {
					$fileTargetClean = 'Deck/' . $fileTargetClean;
				}
				$baseDataDir = $this->config->getSystemValue('datadirectory', '/var/www/html/data');
				$username = $share['uid_owner'] ?? $userId;
				$filePath = $baseDataDir . '/' . $username . '/files/' . $fileTargetClean;
				if (is_readable($filePath)) {
					$fileContents = file_get_contents($filePath);
					$exportPath = 'files/' . $fileTargetClean;
					$exportDestination->addFileContents($exportPath, $fileContents);
				}
			}
		}
	}

	/**
	 * @param int $boardId
	 *
	 * @return array[] [$stacks, $cards, $comments]
	 */
	private function exportStacksCardsCommentsSimple(int $boardId): array {
			$stacks = $this->stackMapper->findAll($boardId);
			$allBoardCards = [];
			$allComments = [];
			foreach ($stacks as $stack) {
				$stackUnarchivedCards = $this->cardMapper->findAll($stack->getId());
				$stackArchivedCards = $this->cardMapper->findAllArchived($stack->getId());
				$allBoardCards = array_merge($allBoardCards, $stackUnarchivedCards, $stackArchivedCards);
				$stackAllCards = array_merge($stackUnarchivedCards, $stackArchivedCards);
				foreach ($stackAllCards as $card) {
					$comments = $this->commentService->exportAllForCard($card->getId());
					$allComments = array_merge($allComments, $comments);
				}
			}
			return [$stacks, $allBoardCards, $allComments];
	}

	/**
	 * {@inheritDoc}
	 */
	public function import(
		IUser $user,
		IImportSource $importSource,
		OutputInterface $output,
	): void {
		if ($importSource->getMigratorVersion($this->getId()) === null) {
			$output->writeln('No version for migrator ' . $this->getId() . ' (' . static::class . '), skipping import…');
			return;
		}
		$output->writeln('Importing boards, cards, comments, shares...');

		$boards = json_decode($importSource->getFileContents(self::BOARDS), true, self::JSON_DEPTH, self::JSON_OPTIONS);
		$userId = $user->getUID();
		$connection = $this->boardMapper->getDbConnection();
		$connection->beginTransaction();

		try {
			$boardIdMap = [];
			$cardIdMap = [];
			$stackIdMap = [];
			$commentIdMap = [];
			foreach ($boards as $board) {
				$newBoard = $this->boardService->importBoard($board, $userId);
				$boardIdMap[$board['id']] = $newBoard->getId();

				$this->importBoardLabels($importSource, $newBoard, $board['id']);
				$this->importBoardAcls($importSource, $newBoard, $board['id']);
			}

			$this->importBoardStacks($importSource, $boardIdMap, $stackIdMap);
			$this->importStackCards($importSource, $stackIdMap, $cardIdMap);
			$this->importCardLabels($importSource, $cardIdMap, $boardIdMap);
			$this->importCardAssignments($importSource, $cardIdMap);
			$this->importCardComments($importSource, $cardIdMap, $commentIdMap);
			$this->importCardAttachment($importSource, $userId, $cardIdMap);


			$connection->commit();
		} catch (\Throwable $e) {
			$connection->rollBack();
			throw $e;
		}

		$output->writeln('Import completed.');
	}

	/**
	 * @param IImportSource $importSource
	 * @param string $userId
	 * @param array $cardIdMap
	 *
	 * @return void
	 */
	private function importCardAttachment(IImportSource $importSource, string $userId, array $cardIdMap): void {
		$cardAttachments = json_decode($importSource->getFileContents(self::CARD_ATTACHMENTS), true);
		$userFolder = $this->rootFolder->getUserFolder($userId);
		try {
			$deckFolder = $userFolder->get('Deck');
		} catch (NotFoundException $e) {
			$deckFolder = $userFolder->newFolder('Deck');
		}

		foreach ($cardAttachments as $share) {
			$this->importDeckAttachment($share, $deckFolder, $importSource, $cardIdMap, $userId);
		}
	}

	/**
	 * @param array $share
	 * @param Folder $deckFolder
	 * @param IImportSource $importSource
	 * @param array $cardIdMap
	 * @param string $userId
	 */
	private function importDeckAttachment(
		array $share,
		Folder $deckFolder,
		IImportSource $importSource,
		array $cardIdMap,
		string $userId,
	): void {
		$fileTarget = $share['file_target'];
		$fileTargetClean = str_replace('{DECK_PLACEHOLDER}/', '', ltrim($fileTarget, '/'));
		if (strpos($fileTargetClean, 'Deck/') !== 0) {
			$fileTargetClean = 'Deck/' . $fileTargetClean;
		}
		$importPath = 'files/' . $fileTargetClean;
		$relativePath = substr($fileTargetClean, strlen('Deck/'));
		$parts = explode('/', $relativePath);
		if (empty($parts)) {
			return;
		}

		$currentFolder = $this->traverseOrCreateFolders($deckFolder, $parts);
		$fileName = array_pop($parts);
		if ($fileName === null) {
			return;
		}

		$fileId = null;
		if ($currentFolder->nodeExists($fileName)) {
			$file = $currentFolder->get($fileName);
			$fileId = $file->getId();
		} else {
			try {
				$fileContents = $importSource->getFileContents($importPath);
			} catch (\Throwable $e) {
				return;
			}
			$file = $currentFolder->newFile($fileName);
			$file->putContent($fileContents);
			$fileId = $file->getId();
		}

		$newCardId = $cardIdMap[$share['share_with']] ?? $share['share_with'];
		$this->filesAppService->importDeckSharesForCard($newCardId, $share, $fileId, $userId);
	}

	/**
	 * @param Folder $baseFolder
	 * @param array $parts
	 * @return Folder
	 */
	private function traverseOrCreateFolders(Folder $baseFolder, array $parts): Folder {
		$currentFolder = $baseFolder;
		for ($i = 0, $n = count($parts) - 1; $i < $n; $i++) {
			$part = $parts[$i];
			try {
				$currentFolder = $currentFolder->get($part);
			} catch (NotFoundException $e) {
				$currentFolder = $currentFolder->newFolder($part);
			}
		}
		return $currentFolder;
	}

	/**
	 * @param IImportSource $importSource
	 * @param Board $newBoard
	 * @param int $oldBoardId
	 *
	 * @return void
	 */
	private function importBoardLabels(IImportSource $importSource, Board $newBoard, int $oldBoardId): void {
		$boardLabels = json_decode($importSource->getFileContents(self::BOARD_LABELS), true);
		foreach ($boardLabels as $label) {
			if ($label['boardId'] === $oldBoardId) {
				$this->labelService->importBoardLabel($newBoard, $label);
			}
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param Board $newBoard
	 * @param int $oldBoardId
	 *
	 * @return void
	 */
	private function importBoardAcls(IImportSource $importSource, Board $newBoard, int $oldBoardId): void {
		$boardAcls = json_decode($importSource->getFileContents(self::BOARD_ACLS), true);
		foreach ($boardAcls as $acl) {
			if ($acl['boardId'] === $oldBoardId) {
				$this->boardService->importAcl($newBoard, $acl);
			}
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param array $boardIdMap
	 * @param array $stackIdMap
	 *
	 * @return void
	 */
	private function importBoardStacks(IImportSource $importSource, array $boardIdMap, array &$stackIdMap): void {
		$stacks = json_decode($importSource->getFileContents(self::BOARD_STACKS), true, self::JSON_DEPTH, self::JSON_OPTIONS);
		foreach ($stacks as $stack) {
			$newStackId = $this->stackService->importStack($boardIdMap[$stack['boardId']], $stack);
			$stackIdMap[$stack['id']] = $newStackId;
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param array $stackIdMap
	 * @param array $cardIdMap
	 *
	 * @return void
	 */
	private function importStackCards(IImportSource $importSource, array $stackIdMap, array &$cardIdMap): void {
		$stackCards = json_decode($importSource->getFileContents(self::STACK_CARDS), true);
		foreach ($stackCards as $card) {
			if (isset($stackIdMap[$card['stackId']])) {
				$newCardId = $this->cardService->importCard($stackIdMap[$card['stackId']], $card);
				$cardIdMap[$card['id']] = $newCardId;
			}
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param array $cardIdMap
	 * @param array $boardIdMap
	 *
	 * @return void
	 */
	private function importCardLabels(IImportSource $importSource, array $cardIdMap, array $boardIdMap): void {
		$cardLabels = json_decode($importSource->getFileContents(self::CARD_LABELS), true);
		foreach ($cardLabels as $label) {
			$newCardId = $cardIdMap[$label['cardId']] ?? null;
			$newBoardId = $boardIdMap[$label['boardId']] ?? null;
			if ($newCardId && $newBoardId) {
				$this->cardService->importLabels($newCardId, $newBoardId, $label);
			}
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param array $cardIdMap
	 * @param array $commentIdMap
	 *
	 * @return void
	 */
	private function importCardComments(IImportSource $importSource, array $cardIdMap, array $commentIdMap): void {
		$cardComments = json_decode($importSource->getFileContents(self::CARD_COMMENTS), true);

		foreach ($cardComments as $comment) {
			$cardId = $cardIdMap[$comment['objectId']] ?? null;
			$parentId = '0';
			$replyToId = $comment['replyTo']['id'] ?? null;
			if (isset($replyToId) && isset($commentIdMap[$replyToId])) {
				$parentId = (string)$commentIdMap[$replyToId];
			}
			$newCommentId = $this->commentService->importComment($cardId, $comment, $parentId);
			$commentIdMap[$comment['id']] = $newCommentId;
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param array $cardIdMap
	 *
	 * @return void
	 */
	private function importCardAssignments(IImportSource $importSource, array $cardIdMap): void {
		$cardAssignedUsers = json_decode($importSource->getFileContents(self::CARD_ASSIGNED), true);
		foreach ($cardAssignedUsers as $assignedUser) {
			if (isset($cardIdMap[$assignedUser['cardId']])) {
				$this->assignmentService->importAssignedUser($cardIdMap[$assignedUser['cardId']], $assignedUser);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getId(): string {
		return 'decks';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Decks');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string {
		return $this->l10n->t('All Boards, cards, comments, shares, and relations');
	}
}
