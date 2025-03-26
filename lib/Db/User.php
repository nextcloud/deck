<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\IUserManager;

class User extends RelationalObject {
	private IUserManager $userManager;
	public function __construct($uid, IUserManager $userManager) {
		$this->userManager = $userManager;
		parent::__construct($uid, function ($object) {
			return $this->userManager->get($object->getPrimaryKey());
		});
	}

	public function getObjectSerialization() {
		return [
			'uid' => $this->getObject()->getUID(),
			'displayname' => $this->getDisplayName(),
			'type' => Acl::PERMISSION_TYPE_USER
		];
	}

	public function getUID() {
		return $this->getPrimaryKey();
	}

	public function getDisplayName() {
		return $this->userManager->getDisplayName($this->getPrimaryKey());
	}
}
