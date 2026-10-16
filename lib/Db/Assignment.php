<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use JsonSerializable;

class Assignment extends RelationalEntity implements JsonSerializable {
	protected $participant;
	protected $cardId;
	protected $type;

	public const TYPE_USER = Acl::PERMISSION_TYPE_USER;
	public const TYPE_GROUP = Acl::PERMISSION_TYPE_GROUP;
	public const TYPE_CIRCLE = Acl::PERMISSION_TYPE_CIRCLE;
	public const TYPE_REMOTE = Acl::PERMISSION_TYPE_REMOTE;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('cardId', 'integer');
		$this->addType('type', 'integer');
		$this->addResolvable('participant');
	}

	public function getTypeString(): string {
		return match ($this->getType()) {
			self::TYPE_USER => 'user',
			self::TYPE_GROUP => 'group',
			self::TYPE_CIRCLE => 'circle',
			default => 'unknown',
		};
	}
}
