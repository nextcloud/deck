<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCP\AppFramework\Db\Entity;
use OCP\Comments\IComment;

abstract class ABoardImportService {
	/** @var string */
	public static $name = '';
	private BoardImportService $boardImportService;
	protected bool $needValidateData = true;
	/** @var Stack[] */
	protected array $stacks = [];
	/** @var Label[] */
	protected array $labels = [];
	/** @var Card[] */
	protected array $cards = [];
	/** @var Acl[] */
	protected array $acls = [];
	/** @var IComment[][] */
	protected array $comments = [];
	/** @var Assignment[] */
	protected array $assignments = [];
	/** @var int[][] */
	protected array $labelCardAssignments = [];

	/**
	 * Configure import service
	 */
	abstract public function bootstrap(): void;

	public function getBoards(): array {
		return [$this->getImportService()->getData()];
	}

	abstract public function getBoard(): ?Board;

	/**
	 * @return Acl[]
	 */
	abstract public function getAclList(): array;

	/**
	 * @return Stack[]
	 */
	abstract public function getStacks(): array;

	/**
	 * @return Card[]
	 */
	abstract public function getCards(): array;

	abstract public function getCardAssignments(): array;

	/**
	 * @return array<int, array<int, int>>
	 */
	abstract public function getCardLabelAssignment(): array;

	/**
	 * @return array<int, array<string, IComment>>
	 */
	abstract public function getComments(): array;

	/** @return Label[] */
	abstract public function getLabels(): array;

	abstract public function validateUsers(): void;

	abstract public function getJsonSchemaPath(): string;

	public function updateStack(string $id, Stack $stack): void {
		$this->stacks[$id] = $stack;
	}

	public function updateCard(string $id, Card $card): void {
		$this->cards[$id] = $card;
	}

	public function updateLabel(string $code, Label $label): void {
		$this->labels[$code] = $label;
	}

	public function updateAcl(string $code, Acl $acl): void {
		$this->acls[$code] = $acl;
	}

	public function updateComment(int $cardId, string $commentId, IComment $comment): void {
		$this->comments[$cardId][$commentId] = $comment;
	}

	public function updateCardAssignment(int $cardId, int $assignmentId, Entity $assignment): void {
		$this->assignments[$cardId][$assignmentId] = $assignment;
	}

	public function updateCardLabelsAssignment(int $cardId, int $assignmentId, int $labelId): void {
		$this->labelCardAssignments[$cardId][$assignmentId] = $labelId;
	}

	public function setImportService(BoardImportService $service): void {
		$this->boardImportService = $service;
	}

	public function getImportService(): BoardImportService {
		return $this->boardImportService;
	}

	public function needValidateData(): bool {
		return $this->needValidateData;
	}

	public function reset(): void {
		// FIXME: Would be cleaner if we could just get a new instance per board
		// but currently https://github.com/nextcloud/deck/blob/7d820aa3f9fc69ada8188549b9a2fbb9093ffb95/lib/Service/Importer/BoardImportService.php#L194 returns a singleton
		$this->labels = [];
		$this->stacks = [];
		$this->acls = [];
		$this->cards = [];
	}
}
