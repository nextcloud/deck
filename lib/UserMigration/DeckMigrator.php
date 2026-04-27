<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\UserMigration;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\ShareFileAttachmentExportService;
use OCP\Comments\ICommentsManager;
use OCP\Files\IAppData;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\ISizeEstimationMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Symfony\Component\Console\Output\OutputInterface;

class DeckMigrator implements IMigrator, ISizeEstimationMigrator {
	use TMigratorBasicVersionHandling;

	protected const FILE_BOARDS = 'boards.json';

	protected const JSON_DEPTH = 512;
	protected const JSON_OPTIONS = JSON_THROW_ON_ERROR;

	public function __construct(
		protected IL10N $l10n,
		protected BoardMapper $boardMapper,
		protected StackMapper $stackMapper,
		protected CardMapper $cardMapper,
		protected LabelMapper $labelMapper,
		protected AclMapper $aclMapper,
		protected AssignmentMapper $assignmentMapper,
		protected AttachmentMapper $attachmentMapper,
		protected ICommentsManager $commentsManager,
		protected IAppData $appData,
		protected ShareFileAttachmentExportService $shareFileAttachmentExportService,
		protected BoardService $boardService,
		protected BoardImportService $boardImportService,
		protected PermissionService $permissionService,
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
		$uid = $user->getUID();
		$this->boardService->setUserId($uid);
		$this->permissionService->setUserId($uid);

		try {
			$exportData = $this->buildExportData($uid);
			$jsonData = json_encode($exportData, self::JSON_OPTIONS);

			$exportDestination->addFileContents(
				self::FILE_BOARDS,
				$jsonData
			);
		} catch (\Throwable $e) {
			throw new DeckMigratorException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function import(
		IUser $user,
		IImportSource $importSource,
		OutputInterface $output,
	): void {
		if (!$this->shouldImport($importSource)) {
			return;
		}

		try {
			$data = $this->readImportData($importSource);
			if (empty($data['boards'])) {
				return;
			}
			$this->configureImportService($user->getUID(), $data);
			$this->boardImportService->import();
		} catch (\Throwable $e) {
			throw new DeckMigratorException($e->getMessage(), 0, $e);
		}
	}

	private function buildExportData(string $uid): array {
		$boards = $this->boardMapper->findAllByUser($uid);
		$exportData = ['boards' => []];

		foreach ($boards as $board) {
			// skip if the board is deleted (to align with the export service)
			if ($board->getDeletedAt() > 0) {
				continue;
			}
			$boardWithStacksAndCards = $this->boardService->export($board->getId());
			$this->appendArchivedCards($boardWithStacksAndCards);
			$exportData['boards'][] = $this->serializeBoard($boardWithStacksAndCards, $uid);
		}

		return $exportData;
	}

	private function serializeBoard(object $board, string $uid): array {
		$boardData = $board->jsonSerialize();
		$serializedStacks = [];
		foreach ($board->getStacks() ?? [] as $stack) {
			$stackData = $stack->jsonSerialize();
			$serializedCards = [];
			foreach ($stack->getCards() ?? [] as $card) {
				$serializedCards[] = $this->serializeCard($card, $uid);
			}
			$stackData['cards'] = $serializedCards;
			$serializedStacks[] = $stackData;
		}
		$boardData['stacks'] = $serializedStacks;

		return $boardData;
	}

	private function appendArchivedCards(object $board): void {
		$stacks = $board->getStacks() ?? [];
		if (count($stacks) === 0) {
			return;
		}

		$stackIds = array_map(static fn ($stack) => $stack->getId(), $stacks);
		$archivedCardsByStack = $this->cardMapper->findAllArchivedForStacks($stackIds);

		foreach ($stacks as $stack) {
			$activeCards = $stack->getCards() ?? [];
			$archivedCards = $archivedCardsByStack[$stack->getId()] ?? [];
			if (count($archivedCards) === 0) {
				continue;
			}
			$stack->setCards(array_merge($activeCards, $archivedCards));
		}
	}

	private function serializeCard(object $card, string $uid): array {
		$cardId = $card->getId();

		$cardData = $card->jsonSerialize();
		$cardData['comments'] = $this->serializeCardComments($cardId);
		$cardData['attachments'] = $this->shareFileAttachmentExportService->exportCardAttachments($cardId, $uid);

		return $cardData;
	}

	private function serializeCardComments(int $cardId): array {
		$comments = iterator_to_array($this->commentsManager->getForObject(
			Application::COMMENT_ENTITY_TYPE,
			(string)$cardId
		));
		usort($comments, static function ($firstComment, $secondComment): int {
			return ((int)$firstComment->getId()) <=> ((int)$secondComment->getId());
		});

		$formattedComments = [];
		foreach ($comments as $comment) {
			$formattedComments[] = [
				'id' => $comment->getId(),
				'parentId' => $comment->getParentId(),
				'actorType' => $comment->getActorType(),
				'actorId' => $comment->getActorId(),
				'message' => $comment->getMessage(),
				'creationDateTime' => $comment->getCreationDateTime()->format(\DateTime::ATOM),
				'objectType' => $comment->getObjectType(),
				'objectId' => $comment->getObjectId(),
				'verb' => $comment->getVerb(),
			];
		}

		return $formattedComments;
	}

	private function shouldImport(IImportSource $importSource): bool {
		return $importSource->getMigratorVersion($this->getId()) !== null;
	}

	private function readImportData(IImportSource $importSource): array {
		$fileContents = $importSource->getFileContents(self::FILE_BOARDS);
		$data = json_decode(
			$fileContents,
			true,
			self::JSON_DEPTH,
			self::JSON_OPTIONS
		);

		if ($data === null) {
			throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
		}

		return $data;
	}

	private function configureImportService(string $userId, array $data): void {
		$this->boardImportService->setSystem('DeckJson');
		$this->boardImportService->setConfigInstance((object)[
			'owner' => $userId,
			'uidRelation' => new \stdClass(),
		]);
		$this->boardImportService->setData(json_decode(
			json_encode(['boards' => $data['boards']]),
			false,
			self::JSON_DEPTH,
			self::JSON_OPTIONS
		));
	}

	/**
	 * {@inheritDoc}
	 */
	public function getId(): string {
		return 'deck';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Deck');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string {
		return $this->l10n->t('All boards owned by you including stacks, cards, labels, assignments, and comments');
	}
}
