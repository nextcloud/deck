<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\UserMigration;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\Importer\BoardImportService;
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
		protected AttachmentMapper $attachmentMapper,
		protected ICommentsManager $commentsManager,
		protected IAppData $appData,
		protected ShareFileAttachmentExportService $shareFileAttachmentExportService,
		protected BoardService $boardService,
		protected BoardImportService $boardImportService,
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
			$exportData['boards'][] = $this->serializeBoard($board, $uid);
		}

		return $exportData;
	}

	private function serializeBoard(object $board, string $uid): array {
		$boardId = $board->getId();
		$board->setLabels($this->labelMapper->findAll($boardId));
		$board->setAcl($this->aclMapper->findAll($boardId));

		$stacksById = $this->getBoardStacksById($boardId);
		$this->attachSerializedCardsToStacks($stacksById, $uid);
		$board->setStacks($stacksById);

		return $board->jsonSerialize();
	}

	private function getBoardStacksById(int $boardId): array {
		$stacksById = [];
		foreach ($this->stackMapper->findAll($boardId) as $stack) {
			$stacksById[$stack->getId()] = $stack;
		}
		return $stacksById;
	}

	private function attachSerializedCardsToStacks(array &$stacksById, string $uid): void {
		$stackIds = array_map(static fn($stack) => $stack->getId(), $stacksById);
		if (empty($stackIds)) {
			return;
		}

		$allCardsByStack = $this->cardMapper->findAllForStacks($stackIds);
		$archivedCardsByStack = $this->cardMapper->findAllArchivedForStacks($stackIds);

		foreach ($stacksById as $stackId => $stack) {
			$stackCards = array_merge(
				$allCardsByStack[$stackId] ?? [],
				$archivedCardsByStack[$stackId] ?? []
			);
			$serializedCards = [];
			foreach ($stackCards as $card) {
				$serializedCards[] = $this->serializeCard($card, $uid);
			}
			$stack->setCards($serializedCards);
		}
	}

	private function serializeCard(object $card, string $uid): array {
		$cardId = $card->getId();
		$card->setLabels($this->labelMapper->findAssignedLabelsForCard($cardId));

		$cardData = $card->jsonSerialize();
		$cardData['labels'] = $cardData['labels'] ?? [];
		$cardData['comments'] = $this->serializeCardComments($cardId);
		$cardData['attachments'] = $this->serializeCardAttachments($cardId, $uid);

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

	private function serializeCardAttachments(int $cardId, string $uid): array {
		$formattedAttachments = [];
		foreach ($this->attachmentMapper->findAll($cardId) as $attachment) {
			$formattedAttachments[] = $this->serializeDeckFileAttachment($attachment, $cardId);
		}

		foreach ($this->getShareFileAttachments($cardId, $uid) as $share) {
			$formattedAttachments[] = $share;
		}

		return $formattedAttachments;
	}

	private function serializeDeckFileAttachment(object $attachment, int $cardId): array {
		$attachmentData = $attachment->jsonSerialize();
		if (($attachmentData['type'] ?? null) === 'deck_file') {
			try {
				$folder = $this->appData->getFolder('file-card-' . $cardId);
				$file = $folder->getFile((string)$attachment->getData());
				$attachmentData['contentBase64'] = base64_encode($file->getContent());
			} catch (\Throwable $e) {
			}
		}
		return $attachmentData;
	}

	private function getShareFileAttachments(int $cardId, string $uid): array {
		return $this->shareFileAttachmentExportService->exportCardAttachments($cardId, $uid);
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
