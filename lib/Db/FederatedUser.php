<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\Federation\ICloudId;

class FederatedUser extends RelationalObject {
	private ICloudId $cloudId;

	public function __construct(ICloudId $cloudId) {
		$this->cloudId = $cloudId;
		parent::__construct($cloudId->getId(), $cloudId);
	}

	public function getObjectSerialization(): array {
		return [
			'uid' => $this->cloudId->getId(),
			'displayname' => $this->cloudId->getUser(),
			'remote' => $this->cloudId->getRemote(),
			'type' => Acl::PERMISSION_TYPE_REMOTE
		];
	}
}
