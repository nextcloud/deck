<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

class Acl extends RelationalEntity {
	public const PERMISSION_READ = 0;
	public const PERMISSION_EDIT = 1;
	public const PERMISSION_SHARE = 2;
	public const PERMISSION_MANAGE = 3;

	public const PERMISSION_TYPE_USER = 0;
	public const PERMISSION_TYPE_GROUP = 1;
	public const PERMISSION_TYPE_CIRCLE = 7;

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
}
