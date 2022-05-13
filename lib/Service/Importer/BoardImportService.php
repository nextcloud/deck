<?php
/**
 * @copyright Copyright (c) 2021 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
use OCA\Deck\Service\Importer\Systems\TrelloApiService;
use OCA\Deck\Service\Importer\Systems\TrelloJsonService;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException as CommentNotFoundException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;
use OCP\Server;

class BoardImportService {
	private IUserManager $userManager;
	private BoardMapper $boardMapper;
	private AclMapper $aclMapper;
	private LabelMapper $labelMapper;
	private StackMapper $stackMapper;
	private CardMapper $cardMapper;
	private AssignmentMapper $assignmentMapper;
	private AttachmentMapper $attachmentMapper;
	private ICommentsManager $commentsManager;
	private IEventDispatcher $eventDispatcher;
	private string $system = '';
	private ?ABoardImportService $systemInstance;
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

	public function __construct(
		IUserManager $userManager,
		BoardMapper $boardMapper,
		AclMapper $aclMapper,
		LabelMapper $labelMapper,
		StackMapper $stackMapper,
		AssignmentMapper $assignmentMapper,
		AttachmentMapper $attachmentMapper,
		CardMapper $cardMapper,
		ICommentsManager $commentsManager,
		IEventDispatcher $eventDispatcher
	) {
		$this->userManager = $userManager;
		$this->boardMapper = $boardMapper;
		$this->aclMapper = $aclMapper;
		$this->labelMapper = $labelMapper;
		$this->stackMapper = $stackMapper;
		$this->cardMapper = $cardMapper;
		$this->assignmentMapper = $assignmentMapper;
		$this->attachmentMapper = $attachmentMapper;
		$this->commentsManager = $commentsManager;
		$this->eventDispatcher = $eventDispatcher;
		$this->board = new Board();
		$this->disableCommentsEvents();
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
		try {
			$this->importBoard();
			$this->importAcl();
			$this->importLabels();
			$this->importStacks();
			$this->importCards();
			$this->assignCardsToLabels();
			$this->importComments();
			$this->importCardAssignments();
		} catch (\Throwable $th) {
			throw new BadRequestException($th->getMessage());
		}
	}

	public function validateSystem(): void {
		$allowedSystems = $this->getAllowedImportSystems();
		$allowedSystems = array_column($allowedSystems, 'internalName');
		if (!in_array($this->getSystem(), $allowedSystems)) {
			throw new NotFoundException('Invalid system');
		}
	}

	/**
	 * @param mixed $system
	 * @return self
	 */
	public function setSystem($system): self {
		$this->system = $system;
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

	public function importBoard(): void {
		$board = $this->getImportSystem()->getBoard();
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
			$this->aclMapper->insert($acl);
			$this->getImportSystem()->updateAcl($code, $acl);
		}
		$this->getBoard()->setAcl($aclList);
	}

	public function importLabels(): void {
		$labels = $this->getImportSystem()->getLabels();
		foreach ($labels as $code => $label) {
			$this->labelMapper->insert($label);
			$this->getImportSystem()->updateLabel($code, $label);
		}
		$this->getBoard()->setLabels($labels);
	}

	public function importStacks(): void {
		$stacks = $this->getImportSystem()->getStacks();
		foreach ($stacks as $code => $stack) {
			$this->stackMapper->insert($stack);
			$this->getImportSystem()->updateStack($code, $stack);
		}
		$this->getBoard()->setStacks(array_values($stacks));
	}

	public function importCards(): void {
		$cards = $this->getImportSystem()->getCards();
		foreach ($cards as $code => $card) {
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
				$this->assignCardToLabel(
					$cardId,
					$labelId
				);
				$this->getImportSystem()->updateCardLabelsAssignment($cardId, $assignmentId, $labelId);
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
			foreach ($assignments as $assignmentId => $assignment) {
				$this->assignmentMapper->insert($assignment);
				$this->getImportSystem()->updateCardAssignment($cardId, $assignmentId, $assignment);
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
		if (empty((array) $this->config)) {
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
