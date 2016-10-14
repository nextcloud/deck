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

// db/author.php
namespace OCA\Deck\Db;

use JsonSerializable;

class Acl extends Entity implements JsonSerializable {

	const PERMISSION_READ = 0;
	const PERMISSION_EDIT = 1;
	const PERMISSION_SHARE = 2;
	const PERMISSION_MANAGE = 3;

    public $id;
    protected $participant;
    protected $type;
    protected $boardId;
    protected $permissionWrite;
    protected $permissionInvite;
    protected $permissionManage;
    protected $owner;

    public function __construct() {
        $this->addType('id','integer');
        $this->addType('boardId','integer');
        $this->addType('permissionWrite', 'boolean');
        $this->addType('permissionInvite', 'boolean');
        $this->addType('permissionManage', 'boolean');
        $this->addType('owner', 'boolean');
        $this->addRelation('owner');
    }

    public function getPermission($permission) {
    	switch ($permission) {
			case Acl::PERMISSION_READ:
				return true;
			case Acl::PERMISSION_EDIT:
				return $this->getPermissionWrite();
			case Acl::PERMISSION_SHARE:
				return $this->getPermissionInvite();
			case Acl::PERMISSION_MANAGE:
				return $this->getPermissionManage();
		}
		return false;
	}

    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'participant' => $this->participant,
            'type' => $this->type,
            'boardId' => $this->boardId,
            'permissionWrite' => $this->permissionWrite,
            'permissionInvite' => $this->permissionInvite,
            'permissionManage' => $this->permissionManage,
            'owner' => $this->owner
        ];
    }
}