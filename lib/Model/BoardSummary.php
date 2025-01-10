<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Model;

use OCA\Deck\Db\Board;

class BoardSummary extends Board {
	private Board $board;

	public function __construct(Board $board) {
		parent::__construct();
		$this->board = $board;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle()
		];
	}

	protected function getter(string $name): mixed {
		return $this->board->getter($name);
	}

	public function __call($name, $arguments) {
		return $this->board->__call($name, $arguments);
	}
}
