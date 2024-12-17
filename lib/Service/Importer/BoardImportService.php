<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\BoardImportGetAllowedEvent;
use OCA\Deck\Exceptions\ConflictException;
use OCA\Deck\NotFoundException;
use OCA\Deck\Service\FileService;
use OCA\Deck\Service\Importer\Systems\DeckJsonService;
use OCA\Deck\Service\Importer\Systems\TrelloApiService;
use OCA\Deck\Service\Importer\Systems\TrelloJsonService;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException as CommentNotFoundException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

class BoardImportService {
	private string $system = '';
	private ?ABoardImportService $systemInstance = null;
	private array $allowedSystems = [];
	/**
	 * Data object created from config JSON
	 *
	 * @var \stdClass
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	public $config;
	/**
	 * Data object created from JSON of origin system
	 *
	 * @var \stdClass
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	private $data;
	private Board $board;

	/** @var callable[] */
	private array $errorCollectors = [];
	/** @var callable[] */
	private array $outputCollectors = [];

	public function __construct(
		private IUserManager $userManager,
		private BoardMapper $boardMapper,
		private AclMapper $aclMapper,
		private LabelMapper $labelMapper,
		private StackMapper $stackMapper,
		private AssignmentMapper $assignmentMapper,
		private AttachmentMapper $attachmentMapper,
		private CardMapper $cardMapper,
		private ICommentsManager $commentsManager,
		private IEventDispatcher $eventDispatcher,
		private LoggerInterface $logger,
	) {
		$this->board = new Board();
		$this->disableCommentsEvents();

		$this->config = new \stdClass();
	}

	public function registerErrorCollector(callable $errorCollector): void {
		$this->errorCollectors[] = $errorCollector;
	}

	public function registerOutputCollector(callable $outputCollector): void {
		$this->outputCollectors[] = $outputCollector;
	}

	private function addError(string $message, $exception): void {
		$message .= ' (on board ' . $this->getBoard()->getTitle() . ')';
		foreach ($this->errorCollectors as $errorCollector) {
			$errorCollector($message, $exception);
		}
		$this->logger->error($message, ['exception' => $exception]);
	}

	private function addOutput(string $message): void {
		foreach ($this->outputCollectors as $outputCollector) {
			$outputCollector($message);
		}
	}

	private function disableCommentsEvents(): void {
		if (defined('PHPUNIT_RUN')) {
			return;
		}
		$propertyEventHandlers = new \ReflectionProperty($this->commentsManager, 'eventHandlers');
		$propertyEventHandlers->setAccessible(true);
		$propertyEventHandlers->setValue($this->commentsManager, []);

		$propertyEventHandlerClosures = new \ReflectionProperty($this->commentsManager, 'eventHandlerClosures');
		$propertyEventHandlerClosures->setAccessible(true);
		$propertyEventHandlerClosures->setValue($this->commentsManager, []);
	}

	public function import(): void {
		$this->bootstrap();
		$boards = $this->getImportSystem()->getBoards();
		foreach ($boards as $board) {
			try {
				$this->reset();
				$this->setData($board);
				$this->importBoard();
				$this->importAcl();
				$this->importLabels();
				$this->importStacks();
				$this->importCards();
				$this->assignCardsToLabels();
				$this->importComments();
				$this->importCardAssignments();
			} catch (\Throwable $th) {
				$this->logger->error('Failed to import board', ['exception' => $th]);
				throw new BadRequestException($th->getMessage());
			}
		}
	}

	public function validateSystem(): void {
		$allowedSystems = $this->getAllowedImportSystems();
		$allowedSystems = array_column($allowedSystems, 'internalName');
		if (!in_array($this->getSystem(), $allowedSystems)) {
			throw new NotFoundException('Invalid system: ' . $this->getSystem());
		}
	}

	/**
	 * @param ?string $system
	 * @return self
	 */
	public function setSystem($system): self {
		if ($system) {
			$this->system = $system;
		}
		return $this;
	}

	public function getSystem(): string {
		return $this->system;
	}

	public function addAllowedImportSystem($system): self {
		$this->allowedSystems[] = $system;
		return $this;
	}

	public function getAllowedImportSystems(): array {
		if (!$this->allowedSystems) {
			$this->addAllowedImportSystem([
				'name' => DeckJsonService::$name,
				'class' => DeckJsonService::class,
				'internalName' => 'DeckJson'
			]);
			$this->addAllowedImportSystem([
				'name' => TrelloApiService::$name,
				'class' => TrelloApiService::class,
				'internalName' => 'TrelloApi'
			]);
			$this->addAllowedImportSystem([
				'name' => TrelloJsonService::$name,
				'class' => TrelloJsonService::class,
				'internalName' => 'TrelloJson'
			]);
		}
		$this->eventDispatcher->dispatchTyped(new BoardImportGetAllowedEvent($this));
		return $this->allowedSystems;
	}

	public function getImportSystem(): ABoardImportService {
		if (!$this->getSystem()) {
			throw new NotFoundException('System to import not found');
		}
		if (!is_object($this->systemInstance)) {
			$systemClass = 'OCA\\Deck\\Service\\Importer\\Systems\\' . ucfirst($this->getSystem()) . 'Service';
			$this->systemInstance = Server::get($systemClass);
			$this->systemInstance->setImportService($this);
		}
		return $this->systemInstance;
	}

	public function setImportSystem(ABoardImportService $instance): void {
		$this->systemInstance = $instance;
	}

	public function reset(): void {
		$this->board = new Board();
		$this->getImportSystem()->reset();
	}

	public function importBoard(): void {
		$board = $this->getImportSystem()->getBoard();
		if (!$this->userManager->userExists($board->getOwner())) {
			throw new \Exception('Target owner ' . $board->getOwner() . ' not found. Please provide a mapping through the import config.');
		}

		if ($board) {
			$this->boardMapper->insert($board);
			$this->board = $board;
		}
	}

	public function getBoard(bool $reset = false): Board {
		if ($reset) {
			$this->board = new Board();
		}
		return $this->board;
	}

	public function importAcl(): void {
		$aclList = $this->getImportSystem()->getAclList();
		foreach ($aclList as $code => $acl) {
			try {
				$this->aclMapper->insert($acl);
				$this->getImportSystem()->updateAcl($code, $acl);
			} catch (\Exception $e) {
				$this->addError('Failed to import acl rule for ' . $acl->getParticipant(), $e);

			}
		}
		$this->getBoard()->setAcl($aclList);
	}

	public function importLabels(): void {
		$labels = $this->getImportSystem()->getLabels();
		foreach ($labels as $code => $label) {
			try {
				$this->labelMapper->insert($label);
				$this->getImportSystem()->updateLabel($code, $label);
			} catch (\Exception $e) {
				$this->addError('Failed to import label ' . $label->getTitle(), $e);
			}
		}
		$this->getBoard()->setLabels($labels);
	}

	public function importStacks(): void {
		$stacks = $this->getImportSystem()->getStacks();
		foreach ($stacks as $code => $stack) {
			try {
				$this->stackMapper->insert($stack);
				$this->getImportSystem()->updateStack($code, $stack);
			} catch (\Exception $e) {
				$this->addError('Failed to import list ' . $stack->getTitle(), $e);
			}
		}
		$this->getBoard()->setStacks(array_values($stacks));
	}

	public function importCards(): void {
		$cards = $this->getImportSystem()->getCards();
		foreach ($cards as $code => $card) {
			try {
				$createdAt = $card->getCreatedAt();
				$lastModified = $card->getLastModified();
				$this->cardMapper->insert($card);
				$updateDate = false;
				if ($createdAt && $createdAt !== $card->getCreatedAt()) {
					$card->setCreatedAt($createdAt);
					$updateDate = true;
				}
				if ($lastModified && $lastModified !== $card->getLastModified()) {
					$card->setLastModified($lastModified);
					$updateDate = true;
				}
				if ($updateDate) {
					$this->cardMapper->update($card, false);
				}
				$this->getImportSystem()->updateCard($code, $card);
			} catch (\Exception $e) {
				$this->addError('Failed to import card ' . $card->getTitle(), $e);
			}
		}
	}

	/**
	 * @param mixed $cardId
	 * @param mixed $labelId
	 * @return self
	 */
	public function assignCardToLabel($cardId, $labelId): self {
		$this->cardMapper->assignLabel(
			$cardId,
			$labelId
		);
		return $this;
	}

	public function assignCardsToLabels(): void {
		$data = $this->getImportSystem()->getCardLabelAssignment();
		foreach ($data as $cardId => $assignemnt) {
			foreach ($assignemnt as $assignmentId => $labelId) {
				try {
					$this->assignCardToLabel(
						$cardId,
						$labelId
					);
					$this->getImportSystem()->updateCardLabelsAssignment($cardId, $assignmentId, $labelId);
				} catch (\Exception $e) {
					$this->addError('Failed to assign label ' . $labelId . ' to ' . $cardId, $e);
				}
			}
		}
	}

	public function importComments(): void {
		$allComments = $this->getImportSystem()->getComments();
		foreach ($allComments as $cardId => $comments) {
			foreach ($comments as $commentId => $comment) {
				$this->insertComment($cardId, $comment);
				$this->getImportSystem()->updateComment($cardId, $commentId, $comment);
			}
		}
	}

	private function insertComment(string $cardId, IComment $comment): void {
		$comment->setObject('deckCard', $cardId);
		$comment->setVerb('comment');
		// Check if parent is a comment on the same card
		if ($comment->getParentId() !== '0') {
			try {
				$parent = $this->commentsManager->get($comment->getParentId());
				if ($parent->getObjectType() !== Application::COMMENT_ENTITY_TYPE || $parent->getObjectId() !== $cardId) {
					throw new CommentNotFoundException();
				}
			} catch (CommentNotFoundException $e) {
				throw new BadRequestException('Invalid parent id: The parent comment was not found or belongs to a different card');
			}
		}

		try {
			$this->commentsManager->save($comment);
		} catch (\InvalidArgumentException $e) {
			throw new BadRequestException('Invalid input values');
		} catch (CommentNotFoundException $e) {
			throw new NotFoundException('Could not create comment.');
		}
	}

	public function importCardAssignments(): void {
		$allAssignments = $this->getImportSystem()->getCardAssignments();
		foreach ($allAssignments as $cardId => $assignments) {
			foreach ($assignments as $assignment) {
				try {
					$assignment = $this->assignmentMapper->insert($assignment);
					$this->getImportSystem()->updateCardAssignment($cardId, (string)$assignment->getId(), $assignment);
					$this->addOutput('Assignment ' . $assignment->getParticipant() . ' added');
				} catch (NotFoundException $e) {
					$this->addError('No origin or mapping found for card "' . $cardId . '" and ' . $assignment->getTypeString() . ' assignment "' . $assignment->getParticipant(), $e);
				}
			}
		}
	}

	public function insertAttachment(Attachment $attachment, string $content): Attachment {
		$service = Server::get(FileService::class);
		$folder = $service->getFolder($attachment);

		if ($folder->fileExists($attachment->getData())) {
			$attachment = $this->attachmentMapper->findByData($attachment->getCardId(), $attachment->getData());
			throw new ConflictException('File already exists.', $attachment);
		}

		$target = $folder->newFile($attachment->getData());
		$target->putContent($content);

		$attachment = $this->attachmentMapper->insert($attachment);

		$service->extendData($attachment);
		return $attachment;
	}

	public function setData(\stdClass $data): void {
		$this->data = $data;
	}

	public function getData(): \stdClass {
		return $this->data;
	}

	/**
	 * Define a config
	 *
	 * @param string $configName
	 * @param mixed $value
	 * @return void
	 */
	public function setConfig(string $configName, $value): void {
		if (empty((array)$this->config)) {
			$this->setConfigInstance(new \stdClass);
		}
		$this->config->$configName = $value;
	}

	/**
	 * Get a config
	 *
	 * @param string $configName config name
	 * @return mixed
	 */
	public function getConfig(string $configName) {
		if (!property_exists($this->config, $configName)) {
			return;
		}
		return $this->config->$configName;
	}

	/**
	 * @param \stdClass $config
	 * @return self
	 */
	public function setConfigInstance($config): self {
		$this->config = $config;
		return $this;
	}

	public function getConfigInstance(): \stdClass {
		return $this->config;
	}

	protected function validateConfig(): void {
		$config = $this->getConfigInstance();
		$schemaPath = $this->getJsonSchemaPath();
		$validator = new Validator();
		$newConfig = clone $config;
		$validator->validate(
			$newConfig,
			(object)['$ref' => 'file://' . realpath($schemaPath)],
			Constraint::CHECK_MODE_APPLY_DEFAULTS
		);
		if (!$validator->isValid()) {
			throw new ConflictException('Invalid config file', $validator->getErrors());
		}
		$this->setConfigInstance($newConfig);
		$this->validateOwner();
	}

	public function getJsonSchemaPath(): string {
		return $this->getImportSystem()->getJsonSchemaPath();
	}

	public function validateOwner(): void {
		$owner = $this->userManager->get($this->getConfig('owner'));
		if (!$owner) {
			throw new \LogicException('Owner "' . $this->getConfig('owner')->getUID() . '" not found on Nextcloud. Check setting json.');
		}
		$this->setConfig('owner', $owner);
	}

	protected function validateData(): void {
	}

	public function bootstrap(): void {
		$this->validateSystem();
		$this->validateConfig();
		$this->validateData();
		$this->getImportSystem()->bootstrap();
	}
}
