<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method getTitle(): string
 */
class Label extends RelationalEntity {
	protected $title;
	protected $color;
	protected $boardId;
	protected $cardId;
	protected $lastModified;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('cardId', 'integer');
		$this->addType('lastModified', 'integer');
	}

	public function getETag() {
		return md5((string)$this->getLastModified());
	}
}
