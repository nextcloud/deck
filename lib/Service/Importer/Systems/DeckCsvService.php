<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer\Systems;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Service\Importer\ABoardImportService;
use OCA\Deck\Service\Importer\CsvParser;
use OCP\IUserManager;

class DeckCsvService extends ABoardImportService {
	public static $name = 'Deck CSV';

	protected bool $needValidateData = false;

	private const DEFAULT_LABEL_COLORS = [
		'CC317C', '317CCC', '2EA07B', 'F4A331',
		'9C31CC', 'CC3131', '31CC7C', '3131CC',
		'CC7C31', '7C31CC',
	];

	/** @var array<int, array<string, mixed>> */
	private array $parsedRows = [];

	/** @var array<string, int> stack name => index */
	private array $stackNameIndex = [];

	/** @var array<string, int> label title => index */
	private array $labelNameIndex = [];

	/** @var array<int, array<string, mixed>> */
	private array $tmpCards = [];

	public function __construct(
		private CsvParser $csvParser,
		private IUserManager $userManager,
	) {
	}

	public function bootstrap(): void {
		$this->parseIfNeeded();
	}

	private function parseIfNeeded(): void {
		if (empty($this->parsedRows)) {
			$data = $this->getImportService()->getData();
			$this->parsedRows = $this->csvParser->parse($data->rawCsvContent);
		}
	}

	public function getJsonSchemaPath(): string {
		return implode(DIRECTORY_SEPARATOR, [
			__DIR__,
			'..',
			'fixtures',
			'config-deckCsv-schema.json',
		]);
	}

	public function validateUsers(): void {
	}

	public function getBoard(): Board {
		$board = $this->getImportService()->getBoard();
		$boardTitle = $this->getImportService()->getConfig('boardTitle') ?? 'Imported Board';
		$owner = $this->getImportService()->getConfig('owner');

		$board->setTitle($boardTitle);
		$board->setOwner($owner instanceof \OCP\IUser ? $owner->getUID() : (string)$owner);
		$board->setColor('0800fd');
		$board->setArchived(false);
		$board->setDeletedAt(0);
		$board->setLastModified(time());
		return $board;
	}

	/**
	 * @return Label[]
	 */
	public function getLabels(): array {
		$this->parseIfNeeded();
		$colorIndex = 0;
		foreach ($this->parsedRows as $row) {
			foreach ($row['tags'] as $tag) {
				if (!isset($this->labelNameIndex[$tag])) {
					$this->labelNameIndex[$tag] = $colorIndex;
					$label = new Label();
					$label->setTitle($tag);
					$label->setColor(self::DEFAULT_LABEL_COLORS[$colorIndex % count(self::DEFAULT_LABEL_COLORS)]);
					$label->setBoardId($this->getImportService()->getBoard()->getId());
					$label->setLastModified(time());
					$this->labels[$colorIndex] = $label;
					$colorIndex++;
				}
			}
		}
		return $this->labels;
	}

	/**
	 * @return Stack[]
	 */
	public function getStacks(): array {
		$this->parseIfNeeded();
		$order = 0;
		foreach ($this->parsedRows as $index => $row) {
			$stackName = $row['stackName'] ?? '';
			if ($stackName === '') {
				$stackName = 'Imported';
			}

			if (!isset($this->stackNameIndex[$stackName])) {
				$this->stackNameIndex[$stackName] = count($this->stackNameIndex);
				$stack = new Stack();
				$stack->setTitle($stackName);
				$stack->setBoardId($this->getImportService()->getBoard()->getId());
				$stack->setOrder($order);
				$stack->setLastModified(time());
				$this->stacks[$this->stackNameIndex[$stackName]] = $stack;
				$order++;
			}

			$row['_index'] = $index;
			$row['_stackKey'] = $this->stackNameIndex[$stackName];
			$this->tmpCards[] = $row;
		}
		return $this->stacks;
	}

	/**
	 * @return Card[]
	 */
	public function getCards(): array {
		$this->parseIfNeeded();
		$cards = [];
		$orderPerStack = [];

		foreach ($this->tmpCards as $row) {
			$stackKey = $row['_stackKey'];
			if (!isset($orderPerStack[$stackKey])) {
				$orderPerStack[$stackKey] = 0;
			}

			$card = new Card();
			$card->setTitle($row['title'] ?? '');
			$card->setDescription($row['description'] ?? '');
			$card->setStackId($this->stacks[$stackKey]->getId());
			$card->setType('plain');
			$card->setOrder($orderPerStack[$stackKey]++);

			$owner = $this->getImportService()->getConfig('owner');
			$card->setOwner($owner instanceof \OCP\IUser ? $owner->getUID() : (string)$owner);

			$card->setDuedate($row['duedate']);
			$card->setCreatedAt($row['createdAt'] ? $row['createdAt']->getTimestamp() : null);
			$card->setLastModified($row['lastModified'] ? $row['lastModified']->getTimestamp() : time());

			$cards[$row['_index']] = $card;
		}
		return $cards;
	}

	public function getCardLabelAssignment(): array {
		$cardsLabels = [];
		foreach ($this->tmpCards as $row) {
			$cardId = $this->cards[$row['_index']]->getId();
			foreach ($row['tags'] as $tag) {
				if (isset($this->labelNameIndex[$tag])) {
					$labelId = $this->labels[$this->labelNameIndex[$tag]]->getId();
					$cardsLabels[$cardId][] = $labelId;
				}
			}
		}
		return $cardsLabels;
	}

	public function getCardAssignments(): array {
		$assignments = [];
		foreach ($this->tmpCards as $row) {
			$cardId = $this->cards[$row['_index']]->getId();
			foreach ($row['assignedUsers'] as $displayName) {
				$users = $this->userManager->searchDisplayName($displayName, 1);
				if (!empty($users)) {
					$user = reset($users);
					$assignment = new Assignment();
					$assignment->setCardId($cardId);
					$assignment->setParticipant($user->getUID());
					$assignment->setType(Assignment::TYPE_USER);
					$assignments[$row['_index']][] = $assignment;
				}
			}
		}
		return $assignments;
	}

	/**
	 * Share the board with all assigned users so they can be assigned to cards.
	 *
	 * @return Acl[]
	 */
	public function getAclList(): array {
		$this->parseIfNeeded();
		$acls = [];
		$seenUsers = [];

		foreach ($this->tmpCards as $row) {
			foreach ($row['assignedUsers'] as $displayName) {
				$users = $this->userManager->searchDisplayName($displayName, 1);
				if (!empty($users)) {
					$user = reset($users);
					$uid = $user->getUID();

					// Skip the board owner — they already have access
					$owner = $this->getImportService()->getConfig('owner');
					$ownerUid = $owner instanceof \OCP\IUser ? $owner->getUID() : (string)$owner;
					if ($uid === $ownerUid) {
						continue;
					}

					if (!isset($seenUsers[$uid])) {
						$acl = new Acl();
						$acl->setBoardId($this->getImportService()->getBoard()->getId());
						$acl->setType(Acl::PERMISSION_TYPE_USER);
						$acl->setParticipant($uid);
						$acl->setPermissionEdit(true);
						$acl->setPermissionShare(false);
						$acl->setPermissionManage(false);
						$acls[] = $acl;
						$seenUsers[$uid] = true;
					}
				}
			}
		}

		return $acls;
	}

	/**
	 * @return array<int, array<string, \OCP\Comments\IComment>>
	 */
	public function getComments(): array {
		return [];
	}

	public function getBoards(): array {
		return [$this->getImportService()->getData()];
	}

	public function reset(): void {
		parent::reset();
		$this->parsedRows = [];
		$this->stackNameIndex = [];
		$this->labelNameIndex = [];
		$this->tmpCards = [];
	}
}
