<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

class Circle extends RelationalObject {

	/** @var \OCA\Circles\Model\Circle */
	protected $object;

	public function __construct(\OCA\Circles\Model\Circle $circle) {
		$primaryKey = $circle->getUniqueId();
		parent::__construct($primaryKey, $circle);
	}

	public function getObjectSerialization() {
		return [
			'uid' => $this->object->getUniqueId(),
			'displayname' => $this->object->getDisplayName(),
			'typeString' => '',
			'circleOwner' => $this->object->getOwner(),
			'type' => 7
		];
	}
}
