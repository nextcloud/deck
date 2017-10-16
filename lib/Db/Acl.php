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

class Acl extends RelationalEntity {

	const PERMISSION_READ = 0;
	const PERMISSION_EDIT = 1;
	const PERMISSION_SHARE = 2;
	const PERMISSION_MANAGE = 3;

	const PERMISSION_TYPE_USER = 0;
	const PERMISSION_TYPE_GROUP = 1;

	protected $participant;
	protected $type;
	protected $boardId;
	protected $permissionEdit = false;
	protected $permissionShare = false;
	protected $permissionManage = false;
	protected $owner = false;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('permissionEdit', 'boolean');
		$this->addType('permissionShare', 'boolean');
		$this->addType('permissionManage', 'boolean');
		$this->addType('type', 'integer');
		$this->addType('owner', 'boolean');
		$this->addRelation('owner');
		$this->addResolvable('participant');
	}

	public function getPermission($permission) {
		switch ($permission) {
			case self::PERMISSION_READ:
				return true;
			case self::PERMISSION_EDIT:
				return $this->getPermissionEdit();
			case self::PERMISSION_SHARE:
				return $this->getPermissionShare();
			case self::PERMISSION_MANAGE:
				return $this->getPermissionManage();
		}
		return false;
	}

	public function jsonSerialize() {
		$json = parent::jsonSerialize();
		$json['type'] = $this->getTypeString();
		return $json;
	}
	
	public function getTypeString() {
		if ($this->type === self::PERMISSION_TYPE_GROUP) {
			return 'group';
		}
		return 'user';
	}

	public function setType($type) {
		if (is_numeric($type)) {
			parent::setType($type);
			return;
		}
		// FIXME: Remove when all javascript uses numeric types
		if ($type === 'group' || $type === '1') {
			$typeInt = self::PERMISSION_TYPE_GROUP;
		} else {
			$typeInt = self::PERMISSION_TYPE_USER;
		}
		$this->markFieldUpdated('type');
		$this->type = $typeInt;
	}

}