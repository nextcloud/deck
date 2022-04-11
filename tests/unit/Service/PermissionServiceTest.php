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
use OCA\Deck\Db\User;
use OCA\Deck\NoPermissionException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\IManager;

class PermissionServiceTest extends \Test\TestCase {

	/** @var PermissionService*/
	private $service;
	/** @var ILogger */
	private $logger;
	/** @var AclMapper */
	private $aclMapper;
	/** @var BoardMapper */
	private $boardMapper;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IManager */
	private $shareManager;
	/** @var IConfig */
	private $config;
	/** @var string */
	private $userId = 'admin';

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(ILogger::class);
		$this->request = $this->createMock(IRequest::class);
		$this->circlesService = $this->createMock(CirclesService::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->config = $this->createMock(IConfig::class);

		$this->service = new PermissionService(
			$this->logger,
			$this->circlesService,
			$this->aclMapper,
			$this->boardMapper,
			$this->userManager,
			$this->groupManager,
			$this->shareManager,
			$this->config,
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
		$aclUser->setPermissionEdit(true);
		$aclUser->setPermissionShare(true);
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
		$adminBoard = new Board();
		$adminBoard->setOwner('admin');
		$userBoard = new Board();
		$userBoard->setOwner('user1');

		$this->boardMapper->expects($this->exactly(2))
			->method('find')
			->withConsecutive([123], [234])
			->willReturnOnConsecutiveCalls($adminBoard, $userBoard);

		$this->assertEquals(true, $this->service->userIsBoardOwner(123));
		$this->assertEquals(false, $this->service->userIsBoardOwner(234));
	}

	public function testUserIsBoardOwnerNull() {
		$this->boardMapper->expects($this->once())->method('find')->willThrowException(new DoesNotExistException('board does not exist'));
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
		$aclUser->setPermissionEdit($edit);
		$aclUser->setPermissionShare($share);
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
	public function testCheckPermission($boardId, $permission, $result, $owner = 'foo') {
		// Setup mapper
		$mapper = $this->getMockBuilder(IPermissionMapper::class)->getMock();

		// board owner
		$mapper->expects($this->once())->method('findBoardId')->willReturn($boardId);
		$board = new Board();
		$board->setId($boardId);
		$board->setOwner($owner);
		$board->setAcl($this->getAcls($boardId));
		$this->boardMapper->expects($this->any())->method('find')->willReturn($board);

		$this->shareManager->expects($this->any())
			->method('sharingDisabledForUser')
			->willReturn(false);

		if ($result) {
			$actual = $this->service->checkPermission($mapper, 1234, $permission);
			$this->assertTrue($actual);
		} else {
			$this->expectException(NoPermissionException::class);
			$this->service->checkPermission($mapper, 1234, $permission);
		}
	}

	/** @dataProvider dataCheckPermission */
	public function testCheckPermissionWithoutMapper($boardId, $permission, $result, $owner = 'foo') {
		$mapper = null;
		$board = new Board();
		$board->setId($boardId);
		$board->setOwner($owner);
		$board->setAcl($this->getAcls($boardId));
		if ($boardId === null) {
			$this->boardMapper->expects($this->any())->method('find')->willThrowException(new DoesNotExistException('not found'));
		} else {
			$this->boardMapper->expects($this->any())->method('find')->willReturn($board);
		}

		if ($result) {
			$actual = $this->service->checkPermission($mapper, 1234, $permission);
			$this->assertTrue($actual);
		} else {
			$this->expectException(NoPermissionException::class);
			$this->service->checkPermission($mapper, 1234, $permission);
		}
	}

	public function testCheckPermissionNotFound() {
		$mapper = $this->getMockBuilder(IPermissionMapper::class)->getMock();
		$mapper->expects($this->once())->method('findBoardId')->willThrowException(new NoPermissionException(null));
		$this->expectException(NoPermissionException::class);
		$this->service->checkPermission($mapper, 1234, Acl::PERMISSION_READ);
	}

	private function generateAcl($boardId, $type, $participant, $edit, $manage, $share) {
		$acl = new Acl();
		$acl->setParticipant($participant);
		$acl->setBoardId($boardId);
		$acl->setType($type);
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
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
			if ($acl->getBoardId() === $boardId) {
				$result[] = $acl;
			}
		}
		return $result;
	}

	public function testFindUsersFail() {
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->will($this->throwException(new DoesNotExistException('')));
		$users = $this->service->findUsers(123);
		$this->assertEquals([], $users);
	}

	/**
	 * @param $uid
	 * @return IUser
	 */
	private function mockUser($uid) {
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn($uid);
		return $user;
	}

	public function testFindUsers() {
		$user1 = $this->mockUser('user1');
		$user2 = $this->mockUser('user2');
		$user3 = $this->mockUser('user3');
		$aclUser = new Acl();
		$aclUser->setType(Acl::PERMISSION_TYPE_USER);
		$aclUser->setParticipant('user2');
		$aclGroup = new Acl();
		$aclGroup->setType(Acl::PERMISSION_TYPE_GROUP);
		$aclGroup->setParticipant('group1');

		$board = $this->createMock(Board::class);
		$board->expects($this->once())
			->method('__call')
			->with('getOwner', [])
			->willReturn('user1');
		$this->aclMapper->expects($this->once())
			->method('findAll')
			->with(123)
			->willReturn([$aclUser, $aclGroup]);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($board);
		$this->userManager->expects($this->exactly(2))
			->method('get')
			->withConsecutive(['user1'], ['user2'])
			->willReturnOnConsecutiveCalls($user1, $user2);

		$group = $this->createMock(IGroup::class);
		$group->expects($this->once())
			->method('getUsers')
			->willReturn([$user3]);
		$this->groupManager->expects($this->once())
			->method('get')
			->with('group1')
			->willReturn($group);
		$users = $this->service->findUsers(123);
		$this->assertEquals([
			'user1' => new User($user1),
			'user2' => new User($user2),
			'user3' => new User($user3),
		], $users);
	}
}
