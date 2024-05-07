<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;

class Session extends Entity implements \JsonSerializable {
	public $id;
	protected $userId;
	protected $token;
	protected $lastContact;
	protected $boardId;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('lastContact', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'token' => $this->token,
			'lastContact' => $this->lastContact,
			'boardId' => $this->boardId,
		];
	}
}
