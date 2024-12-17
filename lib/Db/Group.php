<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\IGroup;

class Group extends RelationalObject {
	public function __construct(IGroup $group) {
		$primaryKey = $group->getGID();
		parent::__construct($primaryKey, $group);
	}

	public function getObjectSerialization() {
		return [
			'uid' => $this->object->getGID(),
			'displayname' => $this->object->getDisplayName(),
			'type' => 1
		];
	}
}
