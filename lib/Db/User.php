<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\IUser;
use OCP\IUserManager;

class User extends RelationalObject {
	private IUserManager $userManager;
	public function __construct($uid, IUserManager $userManager) {
		$this->userManager = $userManager;
		parent::__construct($uid, function ($object) {
			return $this->userManager->get($object->getPrimaryKey());
		});
	}

	public function getObjectSerialization(): array {
		return [
			'uid' => $this->getObject()->getUID(),
			'displayname' => $this->getDisplayName(),
			'type' => Acl::PERMISSION_TYPE_USER
		];
	}

	public function getUID(): string {
		return $this->getPrimaryKey();
	}

	public function getDisplayName(): ?string {
		return $this->userManager->getDisplayName($this->getPrimaryKey());
	}

	public function getUserObject(): IUser {
		return $this->getObject();
	}
}
