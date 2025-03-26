<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use JsonSerializable;

class RelationalObject implements JsonSerializable {
	protected $primaryKey;
	protected $object;

	/**
	 * RelationalObject constructor.
	 *
	 * @param $primaryKey string
	 * @param callable|mixed $object
	 */
	public function __construct($primaryKey, $object) {
		$this->primaryKey = $primaryKey;
		$this->object = $object;
	}

	public function jsonSerialize(): array {
		return array_merge(
			['primaryKey' => $this->primaryKey],
			$this->getObjectSerialization()
		);
	}

	public function getObject() {
		if (is_callable($this->object)) {
			$this->object = call_user_func($this->object, $this);
		}

		return $this->object;
	}

	/**
	 * This method should be overwritten if object doesn't implement \JsonSerializable
	 *
	 * @throws \Exception
	 */
	public function getObjectSerialization() {
		if ($this->getObject() instanceof JsonSerializable) {
			return $this->getObject()->jsonSerialize();
		} else {
			throw new \Exception('jsonSerialize is not implemented on ' . get_class($this->getObject()));
		}
	}

	public function getPrimaryKey(): string {
		return $this->primaryKey;
	}
}
