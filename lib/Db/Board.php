<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method int getId()
 * @method string getTitle()
 * @method int getShared()
 * @method bool getArchived()
 * @method int getDeletedAt()
 * @method int getLastModified()
 */
class Board extends RelationalEntity {
	protected $title;
	protected $owner;
	protected $color;
	protected $archived = false;
	/** @var Label[]|null */
	protected $labels = null;
	/** @var Acl[]|null */
	protected $acl = null;
	protected $permissions = [];
	protected $users = [];
	protected $shared;
	protected $stacks = [];
	protected $activeSessions = [];
	protected $deletedAt = 0;
	protected $lastModified = 0;

	protected $settings = [];

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('shared', 'integer');
		$this->addType('archived', 'boolean');
		$this->addType('deletedAt', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addRelation('labels');
		$this->addRelation('acl');
		$this->addRelation('shared');
		$this->addRelation('users');
		$this->addRelation('activeSessions');
		$this->addRelation('permissions');
		$this->addRelation('stacks');
		$this->addRelation('settings');
		$this->addResolvable('owner');
		$this->shared = -1;
	}

	public function jsonSerialize(): array {
		$json = parent::jsonSerialize();
		if ($this->shared === -1) {
			unset($json['shared']);
		}
		// FIXME: Ideally the API responses should follow the internal data structure and return null if the labels/acls have not been fetched from the db
		// however this would be a breaking change for consumers of the API
		$json['acl'] = $this->acl ?? [];
		$json['labels'] = $this->labels ?? [];
		return $json;
	}

	/**
	 * @param Label[] $labels
	 */
	public function setLabels($labels) {
		$this->labels = $labels;
	}

	/**
	 * @param Acl[] $acl
	 */
	public function setAcl($acl) {
		$this->acl = $acl;
	}

	public function getETag() {
		return md5((string)$this->getLastModified());
	}

	/** @returns Acl[]|null */
	public function getAcl(): ?array {
		return $this->acl;
	}

	/** @returns Label[]|null */
	public function getLabels(): ?array {
		return $this->labels;
	}
}
