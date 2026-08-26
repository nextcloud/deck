<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method int getId()
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getShared()
 * @method void setShared(int $shared)
 * @method bool isArchived()
 * @method bool getArchived()
 * @method void setArchived(bool $archived)
 * @method int getDeletedAt()
 * @method void setDeletedAt(int $deletedAt)
 * @method int getLastModified()
 * @method void setLastModified(int $lastModified)
 * @method string getOwner()
 * @method void setOwner(string $owner)
 * @method string|null getTeamId()
 * @method void setTeamId(?string $teamId)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method void setShareToken(string $shareToken)
 * @method string getShareToken()
 * @method void setExternalId(int $externalId)
 * @method int | null getExternalId()
 * @method string|null getSwimlaneMode()
 * @method void setSwimlaneMode(?string $swimlaneMode)
 * @method string|null getSwimlaneLabelOrder()
 * @method void setSwimlaneLabelOrder(?string $swimlaneLabelOrder)
 * @method string|null getSwimlaneUserOrder()
 * @method void setSwimlaneUserOrder(?string $swimlaneUserOrder)
 */
class Board extends RelationalEntity {
	protected $title;
	protected $owner;
	protected $teamId = null;
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
	protected $shareToken = null;
	protected $externalId = null;
	protected $swimlaneMode = null;
	protected $swimlaneLabelOrder = null;
	protected $swimlaneUserOrder = null;

	protected $settings = [];

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('shared', 'integer');
		$this->addType('archived', 'boolean');
		$this->addType('deletedAt', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('shareToken', 'string');
		$this->addType('externalId', 'integer');
		$this->addType('teamId', 'string');
		$this->addType('swimlaneMode', 'string');
		$this->addType('swimlaneLabelOrder', 'string');
		$this->addType('swimlaneUserOrder', 'string');
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

	#[\Override]
	public function jsonSerialize(): array {
		$json = parent::jsonSerialize();
		if ($this->shared === -1) {
			unset($json['shared']);
		}
		// FIXME: Ideally the API responses should follow the internal data structure and return null if the labels/acls have not been fetched from the db
		// however this would be a breaking change for consumers of the API
		$json['acl'] = $this->acl ?? [];
		$json['labels'] = $this->labels ?? [];
		// Served under 'settings' by BoardService::enrichWithBoardSettings, not top level.
		unset($json['swimlaneMode'], $json['swimlaneLabelOrder'], $json['swimlaneUserOrder']);
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
