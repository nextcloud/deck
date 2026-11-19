<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

class BoardView extends RelationalEntity implements \JsonSerializable {
	protected $boardId;
	protected $name;
	protected $filters;
	protected $owner;
	protected $createdAt;
	protected $lastModifiedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('lastModifiedAt', 'integer');
	}

	public function jsonSerialize(): array {
		$json = parent::jsonSerialize();
		if (isset($json['filters']) && is_string($json['filters'])) {
			$json['filters'] = json_decode($json['filters'], true) ?? [];
		}
		return $json;
	}
}
