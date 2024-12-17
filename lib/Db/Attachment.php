<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

class Attachment extends RelationalEntity {
	protected $cardId;
	protected $type;
	protected $data;
	protected $lastModified = 0;
	protected $createdAt = 0;
	protected $createdBy;
	protected $deletedAt = 0;
	protected $extendedData = [];

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('cardId', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('deletedAt', 'integer');
		$this->addResolvable('createdBy');
		$this->addRelation('extendedData');
	}
}
