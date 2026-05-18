<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Event;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

class BoardUpdatedEvent extends Event implements IWebhookCompatibleEvent {
	private $boardId;

	public function __construct(int $boardId) {
		parent::__construct();

		$this->boardId = $boardId;
	}

	public function getBoardId(): int {
		return $this->boardId;
	}

	public function getWebhookSerializable(): array {
		return [
			'boardId' => $this->boardId,
		];
	}
}
