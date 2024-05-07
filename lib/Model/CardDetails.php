<?php
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Model;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;

class CardDetails extends Card {
	private Card $card;
	private ?Board $board;

	public function __construct(Card $card, ?Board $board = null) {
		parent::__construct();
		$this->card = $card;
		$this->board = $board;
	}

	public function setBoard(?Board $board): void {
		$this->board = $board;
	}

	public function jsonSerialize(array $extras = []): array {
		$array = parent::jsonSerialize();
		$array['overdue'] = $this->getDueStatus();

		unset($array['notified']);
		unset($array['descriptionPrev']);
		unset($array['relatedStack']);
		unset($array['relatedBoard']);

		$array = $this->card->jsonSerialize();
		unset($array['notified'], $array['descriptionPrev'], $array['relatedStack'], $array['relatedBoard']);

		$array['overdue'] = $this->getDueStatus();
		$this->appendBoardDetails($array);

		return $array;
	}

	private function getDueStatus(): int {
		$diffDays = $this->getDaysUntilDue();
		if ($diffDays === null || $diffDays > 1) {
			return static::DUEDATE_FUTURE;
		}
		if ($diffDays === 1) {
			return static::DUEDATE_NEXT;
		}
		if ($diffDays === 0) {
			return static::DUEDATE_NOW;
		}

		return static::DUEDATE_OVERDUE;
	}

	private function appendBoardDetails(&$array): void {
		if (!$this->board) {
			return;
		}

		$array['boardId'] = $this->board->id;
		$array['board'] = (new BoardSummary($this->board))->jsonSerialize();
	}

	protected function getter(string $name): mixed {
		return $this->card->getter($name);
	}

	public function __call(string $methodName, array $args) {
		return $this->card->__call($methodName, $args);
	}
}
