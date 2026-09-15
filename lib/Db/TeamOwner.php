<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCA\Circles\Model\Circle;

/**
 * Keeps the real user id as primary key and exposing the team display name in API responses.
 */
class TeamOwner extends RelationalObject {

	/** @var Circle */
	protected $object;

	public function __construct(string $ownerUserId, Circle $circle) {
		parent::__construct($ownerUserId, $circle);
	}

	public function getObjectSerialization(): array {
		return [
			'uid' => $this->getPrimaryKey(),
			'displayname' => $this->object->getDisplayName(),
			'type' => Acl::PERMISSION_TYPE_CIRCLE,
			'teamId' => $this->object->getSingleId(),
		];
	}
}
