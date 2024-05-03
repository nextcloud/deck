<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);


namespace OCA\Deck\Event;

use OCP\EventDispatcher\Event;

class SessionClosedEvent extends Event {
	private $boardId;
	private $userId;
	
	public function __construct(int $boardId, string $userId) {
		parent::__construct();

		$this->boardId = $boardId;
		$this->userId = $userId;
	}

	public function getBoardId(): int {
		return $this->boardId;
	}
	public function getUserId(): string {
		return $this->userId;
	}
}
