<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer\Systems;

use OC\Comments\Comment;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Service\Importer\ABoardImportService;
use OCP\IUser;
use OCP\IUserManager;

class DeckJsonService extends ABoardImportService {
	public static $name = 'Deck JSON';
	/** @var IUser[] */
	private array $members = [];
	private array $tmpCards = [];

	public function __construct(
		private IUserManager $userManager,
	) {
	}

	public function bootstrap(): void {
		$this->validateUsers();
	}

	public function getJsonSchemaPath(): string {
		return implode(DIRECTORY_SEPARATOR, [
			__DIR__,
			'..',
			'fixtures',
			'config-deckJson-schema.json',
		]);
	}

	public function validateUsers(): void {
		$relation = $this->getImportService()->getConfig('uidRelation');
		if (empty($relation)) {
			return;
		}
		foreach ($relation as $exportUid => $nextcloudUid) {
			if (!is_string($nextcloudUid) && !is_numeric($nextcloudUid)) {
				throw new \LogicException('User on setting uidRelation is invalid');
			}
			$nextcloudUid = (string)$nextcloudUid;
			$this->getImportService()->getConfig('uidRelation')->$exportUid = $this->userManager->get($nextcloudUid);
			if (!$this->getImportService()->getConfig('uidRelation')->$exportUid) {
				throw new \LogicException('User on setting uidRelation not found: ' . $nextcloudUid);
			}
			$this->members[$exportUid] = $this->getImportService()->getConfig('uidRelation')->$exportUid;
		}
	}

	public function mapMember($uid): ?string {
		$ownerMap = $this->mapOwner($uid);
		$sourceId = ($this->getImportService()->getData()->owner->primaryKey ?? $this->getImportService()->getData()->owner);

		if ($uid === $sourceId && $ownerMap !== $sourceId) {
			return $ownerMap;
		}

		$uidCandidate = isset($this->members[$uid]) ? $this->members[$uid]?->getUID() ?? null : null;
		if ($uidCandidate) {
			return $uidCandidate;
		}

		if ($this->userManager->userExists($uid)) {
			return $uid;
		}

		return null;
	}

	public function mapOwner(string $uid): string {
		$configOwner = $this->getImportService()->getConfig('owner');
		if ($configOwner) {
			return $configOwner->getUID();
		}

		return $uid;
	}

	public function getCardAssignments(): array {
		$assignments = [];
		foreach ($this->tmpCards as $sourceCard) {
			foreach ($sourceCard->assignedUsers as $idMember) {
				$assignment = new Assignment();
				$assignment->setCardId($this->cards[$sourceCard->id]->getId());
				$assignment->setParticipant($this->mapMember($idMember->participant->uid ?? $idMember->participant));
				$assignment->setType($idMember->participant->type);
				$assignments[$sourceCard->id][] = $assignment;
			}
		}
		return $assignments;
	}

	public function getComments(): array {
		$comments = [];
		foreach ($this->tmpCards as $sourceCard) {
			if (!property_exists($sourceCard, 'comments')) {
				continue;
			}
			$commentsOriginal = $sourceCard->comments;
			foreach ($commentsOriginal as $commentOriginal) {
				$comment = new Comment();
				$comment->setActor($commentOriginal->actorType, $commentOriginal->actorId)
					->setMessage($commentOriginal->message)->setCreationDateTime(\DateTime::createFromFormat('Y-m-d\TH:i:sP', $commentOriginal->creationDateTime));
				$comments[$this->cards[$sourceCard->id]->getId()][$commentOriginal->id] = $comment;
			}
		}
		return $comments;
	}

	public function getCardLabelAssignment(): array {
		$cardsLabels = [];
		foreach ($this->tmpCards as $sourceCard) {
			foreach ($sourceCard->labels as $label) {
				$cardId = $this->cards[$sourceCard->id]->getId();
				if ($this->getImportService()->getData()->id === $label->boardId) {
					$labelId = $this->labels[$label->id]->getId();
					$cardsLabels[$cardId][] = $labelId;
				}
			}
		}
		return $cardsLabels;
	}

	public function getBoard(): Board {
		$board = $this->getImportService()->getBoard();
		if (empty($this->getImportService()->getData()->title)) {
			throw new BadRequestException('Invalid name of board');
		}
		$importBoard = $this->getImportService()->getData();
		$board->setTitle($importBoard->title);
		$board->setOwner($this->mapOwner($importBoard->owner?->uid ?? $importBoard->owner));
		$board->setColor($importBoard->color);
		$board->setArchived($importBoard->archived);
		$board->setDeletedAt($importBoard->deletedAt);
		$board->setLastModified($importBoard->lastModified);
		return $board;
	}

	/**
	 * @return Label[]
	 */
	public function getLabels(): array {
		foreach ($this->getImportService()->getData()->labels as $label) {
			$newLabel = new Label();
			$newLabel->setTitle($label->title);
			$newLabel->setColor($label->color);
			$newLabel->setBoardId($this->getImportService()->getBoard()->getId());
			$newLabel->setLastModified($label->lastModified);
			$this->labels[$label->id] = $newLabel;
		}
		return $this->labels;
	}

	/**
	 * @return Stack[]
	 */
	public function getStacks(): array {
		$return = [];
		foreach ($this->getImportService()->getData()->stacks as $index => $source) {
			if ($source->title) {
				$stack = new Stack();
				$stack->setTitle($source->title);
				$stack->setBoardId($this->getImportService()->getBoard()->getId());
				$stack->setOrder($source->order);
				$stack->setLastModified($source->lastModified);
				$return[$source->id] = $stack;
			}

			if (isset($source->cards)) {
				foreach ($source->cards as $card) {
					$card->stackId = $index;
					$this->tmpCards[] = $card;
				}
			}
		}
		return $return;
	}

	/**
	 * @return Card[]
	 */
	public function getCards(): array {
		$cards = [];
		foreach ($this->tmpCards as $cardSource) {
			$card = new Card();
			$card->setTitle($cardSource->title);
			$card->setLastModified($cardSource->lastModified);
			$card->setLastEditor($cardSource->lastEditor);
			$card->setCreatedAt($cardSource->createdAt);
			$card->setArchived($cardSource->archived);
			$card->setDescription($cardSource->description);
			$card->setStackId($this->stacks[$cardSource->stackId]->getId());
			$card->setType('plain');
			$card->setOrder($cardSource->order);
			$boardOwner = $this->getBoard()->getOwner();
			$card->setOwner($this->mapOwner(is_string($boardOwner) ? $boardOwner : $boardOwner->getUID()));
			$card->setDuedate($cardSource->duedate);
			$cards[$cardSource->id] = $card;
		}
		return $cards;
	}

	/**
	 * @return Acl[]
	 */
	public function getAclList(): array {
		$board = $this->getImportService()->getData();
		$return = [];
		foreach ($board->acl as $aclData) {
			$acl = new Acl();
			$acl->setBoardId($this->getImportService()->getBoard()->getId());
			$acl->setType($aclData->type);
			$participant = $aclData->participant?->primaryKey ?? $aclData->participant;
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
				$participant = $this->mapMember($participant);
			}
			$acl->setParticipant($participant);
			$acl->setPermissionEdit($aclData->permissionEdit);
			$acl->setPermissionShare($aclData->permissionShare);
			$acl->setPermissionManage($aclData->permissionManage);
			if ($participant) {
				$return[] = $acl;
			}
		}
		return $return;
	}

	private function replaceUsernames(string $text): string {
		foreach ($this->getImportService()->getConfig('uidRelation') as $trello => $nextcloud) {
			$text = str_replace($trello, $nextcloud->getUID(), $text);
		}
		return $text;
	}

	public function getBoards(): array {
		// Old format has just the raw board data, new one a key boards
		$data = $this->getImportService()->getData();
		return array_values((array)($data->boards ?? $data));
	}

	public function reset(): void {
		parent::reset();
		$this->tmpCards = [];
	}
}
