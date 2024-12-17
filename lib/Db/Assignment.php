<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use JsonSerializable;

class Assignment extends RelationalEntity implements JsonSerializable {
	public $id;
	protected $participant;
	protected $cardId;
	protected $type;

	public const TYPE_USER = Acl::PERMISSION_TYPE_USER;
	public const TYPE_GROUP = Acl::PERMISSION_TYPE_GROUP;
	public const TYPE_CIRCLE = Acl::PERMISSION_TYPE_CIRCLE;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('cardId', 'integer');
		$this->addType('type', 'integer');
		$this->addResolvable('participant');
	}

	public function getTypeString(): string {
		switch ($this->getType()) {
			case self::TYPE_USER:
				return 'user';
			case self::TYPE_GROUP:
				return 'group';
			case self::TYPE_CIRCLE:
				return 'circle';
		}

		return 'unknown';
	}
}
