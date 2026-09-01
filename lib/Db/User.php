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

	public function __construct(string $uid, IUserManager $userManager) {
		$this->userManager = $userManager;
		parent::__construct($uid, fn () => $this->userManager->get($uid));
	}

	public function getObjectSerialization(): array {
		return [
			'uid' => $this->getUID(),
			'displayname' => $this->getDisplayName(),
			'type' => Acl::PERMISSION_TYPE_USER,
		];
	}

	public function getUID(): string {
		return $this->getPrimaryKey();
	}

	public function getDisplayName(): ?string {
		$user = $this->getObject();
		return $user ? $user->getDisplayName() : $this->getPrimaryKey();
	}

	public function getUserObject(): ?IUser {
		return $this->getObject();
	}
}
