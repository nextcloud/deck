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

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCP\IGroupManager;
use OCP\ILogger;
use PHPUnit_Framework_TestCase;

class PermissionServiceTest extends \PHPUnit_Framework_TestCase {

	private $service;
	private $logger;
	private $aclMapper;
	private $boardMapper;
	private $groupManager;
	private $userId = 'admin';

	public function setUp() {
		$this->logger = $this->request = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()
			->getMock();
		$this->aclMapper = $this->getMockBuilder(AclMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->boardMapper = $this->getMockBuilder(BoardMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->groupManager = $this->getMockBuilder(IGroupManager::class)
			->disableOriginalConstructor()->getMock();

		$this->service = new PermissionService(
			$this->logger,
			$this->aclMapper,
			$this->boardMapper,
			$this->groupManager,
			'admin'
		);
	}


	public function testGetPermissionsOwner() {
		$board = new Board();
		$board->setOwner('admin');
		$this->boardMapper->expects($this->once())->method('find')->with(123)->willReturn($board);
		$this->aclMapper->expects($this->once())
			->method('findAll')
			->willReturn(null);
		$expected = [
			Acl::PERMISSION_READ => true,
			Acl::PERMISSION_EDIT => true,
			Acl::PERMISSION_MANAGE => true,
			Acl::PERMISSION_SHARE => true,
		];
		$this->assertEquals($expected, $this->service->getPermissions(123));
	}
	public function testGetPermissionsAcl() {
		$board = new Board();
		$board->setOwner('admin');
		$this->boardMapper->expects($this->once())->method('find')->with(123)->willReturn($board);
		$aclUser = new Acl();
		$aclUser->setType('user');
		$aclUser->setParticipant('admin');
		$aclUser->setPermissionWrite(true);
		$aclUser->setPermissionInvite(true);
		$aclUser->setPermissionManage(true);
		$this->aclMapper->expects($this->once())
			->method('findAll')
			->willReturn([$aclUser]);
		$expected = [
			Acl::PERMISSION_READ => true,
			Acl::PERMISSION_EDIT => true,
			Acl::PERMISSION_MANAGE => true,
			Acl::PERMISSION_SHARE => true,
		];
		$this->assertEquals($expected, $this->service->getPermissions(123));
	}
	public function testGetPermissionsAclNo() {
		$board = new Board();
		$board->setOwner('user1');
		$this->boardMapper->expects($this->once())->method('find')->with(123)->willReturn($board);
		$this->aclMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);
		$expected = [
			Acl::PERMISSION_READ => false,
			Acl::PERMISSION_EDIT => false,
			Acl::PERMISSION_MANAGE => false,
			Acl::PERMISSION_SHARE => false,
		];
		$this->assertEquals($expected, $this->service->getPermissions(123));
	}

	public function testGetPermission() {
		$board = new Board();
		$board->setOwner('admin');
		$this->boardMapper->expects($this->exactly(4))->method('find')->with(123)->willReturn($board);
		$this->assertEquals(true, $this->service->getPermission(123, Acl::PERMISSION_READ));
		$this->assertEquals(true, $this->service->getPermission(123, Acl::PERMISSION_EDIT));
		$this->assertEquals(true, $this->service->getPermission(123, Acl::PERMISSION_MANAGE));
		$this->assertEquals(true, $this->service->getPermission(123, Acl::PERMISSION_SHARE));
	}

	public function testGetPermissionFail() {
		$board = new Board();
		$board->setOwner('user1');
		$this->boardMapper->expects($this->exactly(4))->method('find')->with(234)->willReturn($board);
		$this->aclMapper->expects($this->exactly(4))->method('findAll')->willReturn([]);
		$this->assertEquals(false, $this->service->getPermission(234, Acl::PERMISSION_READ));
		$this->assertEquals(false, $this->service->getPermission(234, Acl::PERMISSION_EDIT));
		$this->assertEquals(false, $this->service->getPermission(234, Acl::PERMISSION_MANAGE));
		$this->assertEquals(false, $this->service->getPermission(234, Acl::PERMISSION_SHARE));
	}

	public function testUserIsBoardOwner() {
		$board = new Board();
		$board->setOwner('admin');
		$this->boardMapper->expects($this->at(0))->method('find')->with(123)->willReturn($board);
		$this->assertEquals(true, $this->service->userIsBoardOwner(123));
		$board = new Board();
		$board->setOwner('user1');
		$this->boardMapper->expects($this->at(0))->method('find')->with(234)->willReturn($board);
		$this->assertEquals(false, $this->service->userIsBoardOwner(234));
	}

	public function testUserIsBoardOwnerNull() {
		$this->boardMapper->expects($this->once())->method('find')->willReturn(null);
		$this->assertEquals(false, $this->service->userIsBoardOwner(123));
	}

	public function dataTestUserCan() {
		return [
			// participant permissions type
			['admin', false, false, false, 'user', true, false, false, false],
			['admin', true, false, false, 'user', true, true, false, false],
			['admin', true, true, false, 'user', true, true, true, false],
			['admin', true, true, false, 'user', true, true, true, false],
			['admin', true, true, true, 'user', true, true, true, true],
			['user1', false, false, false, 'user', false, false, false, false]
		];
	}
	/** @dataProvider dataTestUserCan */
	public function testUserCan($participant, $edit, $share, $manage, $type, $canRead, $canEdit, $canShare, $canManage) {
		$aclUser = new Acl();
		$aclUser->setType($type);
		$aclUser->setParticipant($participant);
		$aclUser->setPermissionWrite($edit);
		$aclUser->setPermissionInvite($share);
		$aclUser->setPermissionManage($manage);
		$acls = [
			$aclUser
		];
		$this->assertEquals($canRead, $this->service->userCan($acls, Acl::PERMISSION_READ));
		$this->assertEquals($canEdit, $this->service->userCan($acls, Acl::PERMISSION_EDIT));
		$this->assertEquals($canShare, $this->service->userCan($acls, Acl::PERMISSION_SHARE));
		$this->assertEquals($canManage, $this->service->userCan($acls, Acl::PERMISSION_MANAGE));


	}

	public function testUserCanFail() {
		$this->assertFalse($this->service->userCan([], Acl::PERMISSION_EDIT));
	}

}