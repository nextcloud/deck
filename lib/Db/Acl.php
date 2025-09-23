<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method int getBoardId()
 * @method bool isPermissionEdit()
 * @method void setPermissionEdit(bool $permissionEdit)
 * @method bool isPermissionShare()
 * @method void setPermissionShare(bool $permissionShare)
 * @method bool isPermissionManage()
 * @method void setPermissionManage(bool $permissionManage)
 * @method int getType()
 * @method void setType(int $type)
 * @method bool isOwner()
 * @method void setOwner(int $owner)
 *
 */
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

	public function getPermission(int $permission): bool {
		return match ($permission) {
			self::PERMISSION_READ => true,
			self::PERMISSION_EDIT => $this->getPermissionEdit(),
			self::PERMISSION_SHARE => $this->getPermissionShare(),
			self::PERMISSION_MANAGE => $this->getPermissionManage(),
			default => false,
		};
	}
}
