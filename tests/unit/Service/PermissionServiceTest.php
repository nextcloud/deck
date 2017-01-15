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
use OCA\Deck\Db\IPermissionMapper;
use OCA\Deck\NoPermissionException;
use OCP\IGroupManager;
use OCP\ILogger;

class PermissionServiceTest extends \PHPUnit_Framework_TestCase {

    /** @var \PHPUnit_Framework_MockObject_MockObject|PermissionService */
	private $service;
    /** @var \PHPUnit_Framework_MockObject_MockObject|ILogger */
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

    public function dataCheckPermission() {
        return [
            // see getAcls() for set permissions
            [1, Acl::PERMISSION_READ, true],
            [1, Acl::PERMISSION_EDIT, false],
            [1, Acl::PERMISSION_MANAGE, false],
            [1, Acl::PERMISSION_SHARE, false],

            [2, Acl::PERMISSION_READ, true],
            [2, Acl::PERMISSION_EDIT, true],
            [2, Acl::PERMISSION_MANAGE, false],
            [2, Acl::PERMISSION_SHARE, false],

            [3, Acl::PERMISSION_READ, true],
            [3, Acl::PERMISSION_EDIT, false],
            [3, Acl::PERMISSION_MANAGE, true],
            [3, Acl::PERMISSION_SHARE, false],

            [4, Acl::PERMISSION_READ, true],
            [4, Acl::PERMISSION_EDIT, false],
            [4, Acl::PERMISSION_MANAGE, false],
            [4, Acl::PERMISSION_SHARE, true],

            [null, Acl::PERMISSION_READ, false],
            [6, Acl::PERMISSION_READ, false],

            [1, Acl::PERMISSION_READ, true, 'admin'],
            [1, Acl::PERMISSION_EDIT, true, 'admin'],
            [1, Acl::PERMISSION_MANAGE, true, 'admin'],
            [1, Acl::PERMISSION_SHARE, true, 'admin'],
        ];
    }

	/** @dataProvider dataCheckPermission */
	public function testCheckPermission($boardId, $permission, $result, $owner='foo') {
	    // Setup mapper
	    $mapper = $this->getMockBuilder(IPermissionMapper::class)->getMock();

	    // board owner
	    $mapper->expects($this->once())->method('findBoardId')->willReturn($boardId);
        $board = new Board();
        $board->setId($boardId);
        $board->setOwner($owner);
	    $this->boardMapper->expects($this->any())->method('find')->willReturn($board);

        // acl check
        $acls = $this->getAcls($boardId);
        $this->aclMapper->expects($this->any())->method('findAll')->willReturn($acls);


	    if($result) {
            $actual = $this->service->checkPermission($mapper, 1234, $permission);
            $this->assertTrue($actual);
        } else {
            $this->setExpectedException(NoPermissionException::class);
            $this->service->checkPermission($mapper, 1234, $permission);
        }

    }

    /** @dataProvider dataCheckPermission */
    public function testCheckPermissionWithoutMapper($boardId, $permission, $result, $owner='foo') {
        $mapper = null;
        $board = new Board();
        $board->setId($boardId);
        $board->setOwner($owner);
        $this->boardMapper->expects($this->any())->method('find')->willReturn($board);
        $acls = $this->getAcls($boardId);
        $this->aclMapper->expects($this->any())->method('findAll')->willReturn($acls);

        if($result) {
            $actual = $this->service->checkPermission($mapper, 1234, $permission);
            $this->assertTrue($actual);
        } else {
            $this->setExpectedException(NoPermissionException::class);
            $this->service->checkPermission($mapper, 1234, $permission);
        }

    }

    public function testCheckPermissionNotFound() {
        $mapper = $this->getMockBuilder(IPermissionMapper::class)->getMock();
        $mapper->expects($this->once())->method('findBoardId')->willThrowException(new NoPermissionException(null));
        $this->setExpectedException(NoPermissionException::class);
        $this->service->checkPermission($mapper, 1234, Acl::PERMISSION_READ);
    }

    private function generateAcl($boardId, $type, $participant, $write, $manage, $share) {
	    $acl = new Acl();
        $acl->setParticipant($participant);
        $acl->setBoardId($boardId);
	    $acl->setType($type);
        $acl->setPermissionWrite($write);
        $acl->setPermissionInvite($share);
        $acl->setPermissionManage($manage);
        return $acl;
    }

    private function getAcls($boardId) {
        $acls = [
            $this->generateAcl(1, 'user', 'admin', false, false, false),
            $this->generateAcl(2, 'user', 'admin', true, false, false),
            $this->generateAcl(3, 'user', 'admin', false, true, false),
            $this->generateAcl(4, 'user', 'admin', false, false, true),
            $this->generateAcl(5, 'group', 'admin', false, false, false),
            $this->generateAcl(6, 'user', 'foo', false, false, false)
        ];
	    $result = [];
	    foreach ($acls as $acl) {
	        if($acl->getBoardId() === $boardId) {
	            $result[] = $acl;
            }
        }
        return $result;
    }


}
