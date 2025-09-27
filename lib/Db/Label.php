<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method getTitle(): string
 * @method getCustomSettings(): string
 * @method setCustomSettings(string $customSettings)
 */
class Label extends RelationalEntity {
	protected $title;
	protected $color;
	protected $boardId;
	protected $cardId;
	protected $lastModified;
	protected $customSettings;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('cardId', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('customSettings', 'string');
	}

	public function getETag() {
		return md5((string)$this->getLastModified());
	}

	public function getCustomSettingsArray(): array {
		return $this->customSettings ? json_decode($this->customSettings, true) : [];
	}

	public function setCustomSettingsArray(array $customSettings): void {
		$this->setCustomSettings(json_encode($customSettings ?: new \stdClass()));
	}

	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		$data['customSettings'] = $this->getCustomSettingsArray() ?: new \stdClass();

		return $data;
	}
}
