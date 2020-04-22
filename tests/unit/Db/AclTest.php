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

class AclTest extends \Test\TestCase {
	private function createAclUser() {
		$acl = new Acl();
		$acl->setId(1);
		$acl->setParticipant("admin");
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setBoardId(1);
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		return $acl;
	}

	private function createAclGroup() {
		$acl = new Acl();
		$acl->setId(1);
		$acl->setParticipant("administrators");
		$acl->setType(Acl::PERMISSION_TYPE_GROUP);
		$acl->setBoardId(1);
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		return $acl;
	}

	public function testJsonSerialize() {
		$acl = $this->createAclUser();
		$this->assertEquals([
			'id' => 1,
			'participant' => 'admin',
			'type' => Acl::PERMISSION_TYPE_USER,
			'boardId' => 1,
			'permissionEdit' => true,
			'permissionShare' => true,
			'permissionManage' => true,
			'owner' => false
		], $acl->jsonSerialize());
		$acl = $this->createAclGroup();
		$this->assertEquals([
			'id' => 1,
			'participant' => 'administrators',
			'type' => Acl::PERMISSION_TYPE_GROUP,
			'boardId' => 1,
			'permissionEdit' => true,
			'permissionShare' => true,
			'permissionManage' => true,
			'owner' => false
		], $acl->jsonSerialize());
	}

	public function testSetOwner() {
		$acl = $this->createAclUser();
		$acl->setOwner(1);
		$this->assertEquals([
			'id' => 1,
			'participant' => 'admin',
			'type' => Acl::PERMISSION_TYPE_USER,
			'boardId' => 1,
			'permissionEdit' => true,
			'permissionShare' => true,
			'permissionManage' => true,
			'owner' => true
		], $acl->jsonSerialize());
	}


	public function testGetPermission() {
		$acl = $this->createAclUser();
		$this->assertEquals(true, $acl->getPermission(Acl::PERMISSION_READ));
		$this->assertEquals(true, $acl->getPermission(Acl::PERMISSION_EDIT));
		$this->assertEquals(true, $acl->getPermission(Acl::PERMISSION_MANAGE));
		$this->assertEquals(true, $acl->getPermission(Acl::PERMISSION_SHARE));
		$acl->setPermissionEdit(false);
		$acl->setPermissionShare(false);
		$acl->setPermissionManage(false);
		$this->assertEquals(true, $acl->getPermission(Acl::PERMISSION_READ));
		$this->assertEquals(false, $acl->getPermission(Acl::PERMISSION_EDIT));
		$this->assertEquals(false, $acl->getPermission(Acl::PERMISSION_MANAGE));
		$this->assertEquals(false, $acl->getPermission(Acl::PERMISSION_SHARE));
		$this->assertEquals(false, $acl->getPermission(5));
	}
}
