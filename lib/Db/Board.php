<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
