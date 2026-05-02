<?php

declare(strict_types=1);

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

use OC\Federation\CloudFederationFactory;
use OC\Federation\CloudFederationProviderManager;
use OC\Federation\CloudIdManager;
use OC\L10N\L10N;
use OC\Security\SecureRandom;
use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Session;
use OCA\Deck\Db\SessionMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Db\User;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\Validators\BoardServiceValidator;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class BoardServiceTest extends TestCase {

	/** @var BoardService */
	private $service;
	/** @var IConfig */
	private $config;
	/** @var L10N */
	private $l10n;
	/** @var LabelMapper */
	private $labelMapper;
	/** @var AclMapper */
	private $aclMapper;
	/** @var BoardMapper */
	private $boardMapper;
	/** @var StackMapper */
	private $stackMapper;
	/** @var CardMapper */
	private $cardMapper;
	/** @var PermissionService */
	private $permissionService;
	/** @var AssignmentService */
	private $assignmentService;
	/** @var NotificationHelper */
	private $notificationHelper;
	/** @var AssignmentMapper */
	private $assignedUsersMapper;
	/** @var ActivityManager */
	private $activityManager;
	/** @var ChangeHelper */
	private $changeHelper;
	/** @var IEventDispatcher */
	private $eventDispatcher;
	private $userId = 'admin';
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IDBConnection|MockObject */
	private $connection;
	/** @var BoardServiceValidator */
	private $boardServiceValidator;
	/** @var SessionMapper */
	private $sessionMapper;

	/** @var IUserManager */
	private $userManager;
	/** @var CirclesService|MockObject */
	private $circlesService;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(L10N::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->assignmentService = $this->createMock(AssignmentService::class);
		$this->notificationHelper = $this->createMock(NotificationHelper::class);
		$this->assignedUsersMapper = $this->createMock(AssignmentMapper::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->boardServiceValidator = $this->createMock(BoardServiceValidator::class);
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->circlesService = $this->createMock(CirclesService::class);

		$this->service = new BoardService(
			$this->boardMapper,
			$this->stackMapper,
			$this->cardMapper,
			$this->config,
			$this->l10n,
			$this->labelMapper,
			$this->aclMapper,
			$this->permissionService,
			$this->assignmentService,
			$this->notificationHelper,
			$this->assignedUsersMapper,
			$this->activityManager,
			$this->createMock(CloudFederationProviderManager::class),
			$this->createMock(CloudIdManager::class),
			$this->createMock(CloudFederationFactory::class),
			$this->eventDispatcher,
			$this->changeHelper,
			$this->urlGenerator,
			$this->connection,
			$this->boardServiceValidator,
			$this->sessionMapper,
			$this->userManager,
			$this->createMock(SecureRandom::class),
			$this->createMock(ConfigService::class),
			$this->circlesService,
			$this->userId
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
	}

	public function testFindAll() {
		$b1 = new Board();
		$b1->setId(1);
		$b2 = new Board();
		$b2->setId(2);
		$b3 = new Board();
		$b3->setId(3);
		$this->boardMapper->expects($this->once())
			->method('findAllForUser')
			->with('admin')
			->willReturn([$b1, $b2, $b3]);

		$result = $this->service->findAll();
		sort($result);
		$this->assertEquals([$b1, $b2, $b3], $result);
	}

	public function testFind() {
		$b1 = new Board();
		$b1->setId(1);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($b1);
		$this->permissionService->expects($this->any())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$session = $this->createMock(Session::class);
		$this->sessionMapper->expects($this->once())
			->method('findAllActive')
			->with(1)
			->willReturn([$session]);
		$this->assertEquals($b1, $this->service->find(1));
	}

	public function testCreate() {
		$board = new Board();
		$board->setTitle('MyBoard');
		$board->setOwner('admin');
		$board->setColor('00ff00');
		$this->boardMapper->expects($this->once())
			->method('insert')
			->willReturn($board);
		$this->permissionService->expects($this->once())
			->method('canCreate')
			->willReturn(true);
		$b = $this->service->create('MyBoard', 'admin', '00ff00');

		$this->assertEquals($b->getTitle(), 'MyBoard');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getColor(), '00ff00');
		$this->assertCount(4, $b->getLabels());
	}

	public function testCreateDenied() {
		$this->expectException(\OCA\Deck\NoPermissionException::class);
		$board = new Board();
		$board->setTitle('MyBoard');
		$board->setOwner('admin');
		$board->setColor('00ff00');
		$this->permissionService->expects($this->once())
			->method('canCreate')
			->willReturn(false);
		$b = $this->service->create('MyBoard', 'admin', '00ff00');
	}

	public function testUpdate() {
		$board = new Board();
		$board->setId(123);
		$board->setTitle('MyBoard');
		$board->setOwner('admin');
		$board->setColor('00ff00');
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($board);
		$this->boardMapper->expects($this->once())
			->method('update')
			->with($board)
			->willReturn($board);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$this->sessionMapper->expects($this->once())
			->method('findAllActive')
			->willReturn([]);
		$b = $this->service->update(123, 'MyNewNameBoard', 'ffffff', false);

		$this->assertEquals($b->getTitle(), 'MyNewNameBoard');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getColor(), 'ffffff');
		$this->assertEquals($b->getArchived(), false);
	}

	public function testDelete() {
		$board = new Board();
		$board->setId(42);
		$board->setOwner('admin');
		$board->setDeletedAt(0);
		$this->boardMapper->expects($this->once())
			->method('find')
			->willReturn($board);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$this->sessionMapper->expects($this->once())
			->method('findAllActive')
			->with(42)
			->willReturn([]);
		$boardDeleted = clone $board;
		$boardDeleted->setDeletedAt(1);
		$this->boardMapper->expects($this->once())
			->method('update')
			->willReturn($boardDeleted);
		$this->aclMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);
		$this->assertEquals($boardDeleted, $this->service->delete(123));
	}

	public function testAddAcl() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		$acl->resolveRelation('participant', function ($participant) use (&$user) {
			return null;
		});
		$this->notificationHelper->expects($this->once())
			->method('sendBoardShared');
		$this->aclMapper->expects($this->once())
			->method('insert')
			->with($acl)
			->willReturn($acl);
		$this->permissionService->expects($this->any())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$this->assertEquals($acl, $this->service->addAcl(
			123, Acl::PERMISSION_TYPE_USER, 'admin', true, true, true
		));
	}

	public static function dataAddAclExtendPermission() {
		return [
			[[false, false, false], [false, false, false], [false, false, false]],
			[[false, false, false], [true, true, true], [false, false, false]],

			// user has share permissions -> can only reshare with those
			[[false, true, false], [false, false, false], [false, false, false]],
			[[false, true, false], [false, true, false], [false, true, false]],
			[[false, true, false], [true, true, true], [false, true, false]],

			// user has write permissions -> can only reshare with those
			[[true, true, false], [false, false, false], [false, false, false]],
			[[true, true, false], [false, true, false], [false, true, false]],
			[[true, true, false], [true, true, true], [true, true, false]],

			// user has manage permissions -> can upgrade acl permissions
			[[false, false, true], [true, true, true], [true, true, true]],
			[[true, true, true], [false, false, true], [false, false, true]],
		];
	}

	/**
	 * @dataProvider dataAddAclExtendPermission
	 * @param $currentUserAcl
	 * @param $providedAcl
	 * @param $resultingAcl
	 * @throws NoPermissionException
	 * @throws \OCA\Deck\BadRequestException
	 */
	public function testAddAclExtendPermission($currentUserAcl, $providedAcl, $resultingAcl) {
		$existingAcl = new Acl();
		$existingAcl->setBoardId(123);
		$existingAcl->setType(Acl::PERMISSION_TYPE_USER);
		$existingAcl->setParticipant('admin');
		$existingAcl->setPermissionEdit($currentUserAcl[0]);
		$existingAcl->setPermissionShare($currentUserAcl[1]);
		$existingAcl->setPermissionManage($currentUserAcl[2]);

		if ($currentUserAcl[2]) {
			$this->permissionService->expects($this->exactly(2))
				->method('checkPermission')
				->withConsecutive(
					[$this->boardMapper, 123, Acl::PERMISSION_SHARE, null],
					[$this->boardMapper, 123, Acl::PERMISSION_MANAGE, null]
				);
		} else {
			$this->aclMapper->expects($this->once())
				->method('findAll')
				->willReturn([$existingAcl]);

			$this->permissionService->expects($this->exactly(2))
				->method('checkPermission')
				->withConsecutive(
					[$this->boardMapper, 123, Acl::PERMISSION_SHARE, null],
					[$this->boardMapper, 123, Acl::PERMISSION_MANAGE, null]
				)
				->will(
					$this->onConsecutiveCalls(
						true,
						$this->throwException(new NoPermissionException('No permission'))
					)
				);

			$this->permissionService->expects($this->exactly(3))
				->method('userCan')
				->willReturnOnConsecutiveCalls(
					$currentUserAcl[0],
					$currentUserAcl[1],
					$currentUserAcl[2]
				);
		}

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType('user');
		$acl->setParticipant('admin');
		$acl->setPermissionEdit($resultingAcl[0]);
		$acl->setPermissionShare($resultingAcl[1]);
		$acl->setPermissionManage($resultingAcl[2]);
		$acl->resolveRelation('participant', function ($participant) use (&$user) {
			return null;
		});
		$this->permissionService->expects($this->any())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$this->notificationHelper->expects($this->once())
			->method('sendBoardShared');
		$expected = clone $acl;
		$this->aclMapper->expects($this->once())
			->method('insert')
			->with($acl)
			->willReturn($acl);
		$this->eventDispatcher->expects(self::once())
			->method('dispatchTyped')
			->with(new AclCreatedEvent($acl));
		$this->assertEquals($expected, $this->service->addAcl(
			123, Acl::PERMISSION_TYPE_USER, 'admin', $providedAcl[0], $providedAcl[1], $providedAcl[2]
		));
	}

	public function testUpdateAcl() {
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);

		$this->aclMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($acl);
		$this->aclMapper->expects($this->once())
			->method('update')
			->with($acl)
			->willReturn($acl);

		$result = $this->service->updateAcl(
			123, false, false, false
		);

		$this->assertFalse($result->getPermissionEdit());
		$this->assertFalse($result->getPermissionShare());
		$this->assertFalse($result->getPermissionManage());
	}

	public function testDeleteAcl() {
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		$this->aclMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($acl);
		$assignment = new Assignment();
		$assignment->setParticipant('admin');
		$this->assignedUsersMapper->expects($this->once())
			->method('deleteByParticipantOnBoard')
			->with('admin', 123)
			->willReturn([$assignment]);
		$this->assignedUsersMapper->expects($this->never())
			->method('delete')
			->with($assignment);
		$this->aclMapper->expects($this->once())
			->method('delete')
			->with($acl)
			->willReturn($acl);
		$this->eventDispatcher->expects(self::once())
			->method('dispatchTyped')
			->with(new AclDeletedEvent($acl));
		$this->assertEquals($acl, $this->service->deleteAcl(123));
	}

	public function testFindMarksUserAclAsRetainedViaOwnerCircleMembership(): void {
		$board = new Board();
		$board->setId(10);
		$board->setOwner('circle-1');
		$board->setOwnerType(Acl::PERMISSION_TYPE_CIRCLE);

		$userAcl = new Acl();
		$userAcl->setId(501);
		$userAcl->setBoardId(10);
		$userAcl->setType(Acl::PERMISSION_TYPE_USER);
		$userAcl->setParticipant('alice');
		$board->setAcl([$userAcl]);

		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 10, Acl::PERMISSION_READ, null, false);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(10, true, true, false)
			->willReturn($board);

		$this->boardMapper->expects($this->once())
			->method('mapOwner')
			->with($board);

		$this->boardMapper->expects($this->once())
			->method('mapAcl')
			->with($userAcl);

		$this->circlesService->expects($this->once())
			->method('isUserInCircle')
			->with('circle-1', 'alice')
			->willReturn(true);

		$this->permissionService->expects($this->never())
			->method('userCan');

		$this->permissionService->expects($this->once())
			->method('matchPermissions')
			->with($board)
			->willReturn([
				Acl::PERMISSION_READ => true,
				Acl::PERMISSION_EDIT => true,
				Acl::PERMISSION_MANAGE => true,
				Acl::PERMISSION_SHARE => true,
				Board::PERMISSION_OWNER => true,
			]);

		$result = $this->service->find(10, false);
		$this->assertTrue($result->getAcl()[0]->isRetainsAccessViaMembership());
	}

	public function testFindMarksUserAclAsRetainedViaOtherAclMembership(): void {
		$board = new Board();
		$board->setId(11);
		$board->setOwner('bob');
		$board->setOwnerType(Acl::PERMISSION_TYPE_USER);

		$userAcl = new Acl();
		$userAcl->setId(601);
		$userAcl->setBoardId(11);
		$userAcl->setType(Acl::PERMISSION_TYPE_USER);
		$userAcl->setParticipant('alice');

		$groupAcl = new Acl();
		$groupAcl->setId(602);
		$groupAcl->setBoardId(11);
		$groupAcl->setType(Acl::PERMISSION_TYPE_GROUP);
		$groupAcl->setParticipant('devs');

		$board->setAcl([$userAcl, $groupAcl]);

		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 11, Acl::PERMISSION_READ, null, false);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(11, true, true, false)
			->willReturn($board);

		$this->boardMapper->expects($this->once())
			->method('mapOwner')
			->with($board);

		$this->boardMapper->expects($this->exactly(2))
			->method('mapAcl')
			->withConsecutive([$userAcl], [$groupAcl]);

		$this->circlesService->expects($this->never())
			->method('isUserInCircle');

		$this->permissionService->expects($this->once())
			->method('userCan')
			->with(
				$this->callback(static function (array $otherAcls): bool {
					$otherAcls = array_values($otherAcls);
					return count($otherAcls) === 1 && $otherAcls[0]->getId() === 602;
				}),
				Acl::PERMISSION_READ,
				'alice'
			)
			->willReturn(true);

		$this->permissionService->expects($this->once())
			->method('matchPermissions')
			->with($board)
			->willReturn([
				Acl::PERMISSION_READ => true,
				Acl::PERMISSION_EDIT => true,
				Acl::PERMISSION_MANAGE => true,
				Acl::PERMISSION_SHARE => true,
				Board::PERMISSION_OWNER => false,
			]);

		$result = $this->service->find(11, false);
		$this->assertTrue($result->getAcl()[0]->isRetainsAccessViaMembership());
		$this->assertTrue($result->getAcl()[1]->isRetainsAccessViaMembership());
	}

	public function testFindMarksUserAclAsNotRetainedWithoutInheritedAccess(): void {
		$board = new Board();
		$board->setId(12);
		$board->setOwner('bob');
		$board->setOwnerType(Acl::PERMISSION_TYPE_USER);

		$userAcl = new Acl();
		$userAcl->setId(701);
		$userAcl->setBoardId(12);
		$userAcl->setType(Acl::PERMISSION_TYPE_USER);
		$userAcl->setParticipant('alice');
		$board->setAcl([$userAcl]);

		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 12, Acl::PERMISSION_READ, null, false);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(12, true, true, false)
			->willReturn($board);

		$this->boardMapper->expects($this->once())
			->method('mapOwner')
			->with($board);

		$this->boardMapper->expects($this->once())
			->method('mapAcl')
			->with($userAcl);

		$this->circlesService->expects($this->never())
			->method('isUserInCircle');

		$this->permissionService->expects($this->once())
			->method('userCan')
			->with([], Acl::PERMISSION_READ, 'alice')
			->willReturn(false);

		$this->permissionService->expects($this->once())
			->method('matchPermissions')
			->with($board)
			->willReturn([
				Acl::PERMISSION_READ => true,
				Acl::PERMISSION_EDIT => true,
				Acl::PERMISSION_MANAGE => true,
				Acl::PERMISSION_SHARE => true,
				Board::PERMISSION_OWNER => false,
			]);

		$result = $this->service->find(12, false);
		$this->assertFalse($result->getAcl()[0]->isRetainsAccessViaMembership());
	}

	public function testTransferBoardOwnershipToCircle(): void {
		$board = new Board();
		$board->setId(10);
		$board->setOwner('alice');
		$board->setOwnerType(Acl::PERMISSION_TYPE_USER);

		$updatedBoard = new Board();
		$updatedBoard->setId(10);
		$updatedBoard->setOwner('circle-id-xyz');
		$updatedBoard->setOwnerType(Acl::PERMISSION_TYPE_CIRCLE);

		$this->connection->expects($this->once())->method('beginTransaction');
		$this->connection->expects($this->once())->method('commit');

		$this->boardMapper->expects($this->exactly(2))
			->method('find')
			->willReturnOnConsecutiveCalls($board, $updatedBoard);

		$this->circlesService->expects($this->once())
			->method('isCirclesEnabled')
			->willReturn(true);
		$this->circlesService->expects($this->once())
			->method('getCircle')
			->with('circle-id-xyz')
			->willReturn($this->createMock(\OCA\Circles\Model\Circle::class));

		$this->aclMapper->expects($this->once())
			->method('deleteParticipantFromBoard')
			->with(10, Acl::PERMISSION_TYPE_CIRCLE, 'circle-id-xyz');

		// Previous user owner gets an ACL entry when changeContent = false
		$this->aclMapper->expects($this->exactly(2))
			->method('findAll')
			->willReturn([]);
		$this->aclMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(fn ($acl) => $acl);

		$this->boardMapper->expects($this->once())
			->method('transferOwnership')
			->with('alice', 'circle-id-xyz', 10, Acl::PERMISSION_TYPE_CIRCLE);

		// No content remap when target is a circle
		$this->assignedUsersMapper->expects($this->never())->method('remapAssignedUser');
		$this->cardMapper->expects($this->never())->method('remapCardOwner');

		$result = $this->service->transferBoardOwnership(10, 'circle-id-xyz', false, Acl::PERMISSION_TYPE_CIRCLE);
		$this->assertSame($updatedBoard, $result);
	}

	public function testFindMarksCircleAclAsRetainedViaMembership(): void {
		$board = new Board();
		$board->setId(13);
		$board->setOwner('bob');
		$board->setOwnerType(Acl::PERMISSION_TYPE_USER);

		$circleAcl = new Acl();
		$circleAcl->setId(801);
		$circleAcl->setBoardId(13);
		$circleAcl->setType(Acl::PERMISSION_TYPE_CIRCLE);
		$circleAcl->setParticipant('circle-2');
		$board->setAcl([$circleAcl]);

		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 13, Acl::PERMISSION_READ, null, false);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(13, true, true, false)
			->willReturn($board);

		$this->boardMapper->expects($this->once())
			->method('mapOwner')
			->with($board);

		$this->boardMapper->expects($this->once())
			->method('mapAcl')
			->with($circleAcl);

		$this->circlesService->expects($this->never())
			->method('isUserInCircle');

		$this->permissionService->expects($this->never())
			->method('userCan');

		$this->permissionService->expects($this->once())
			->method('matchPermissions')
			->with($board)
			->willReturn([
				Acl::PERMISSION_READ => true,
				Acl::PERMISSION_EDIT => true,
				Acl::PERMISSION_MANAGE => true,
				Acl::PERMISSION_SHARE => true,
				Board::PERMISSION_OWNER => false,
			]);

		$result = $this->service->find(13, false);
		$this->assertTrue($result->getAcl()[0]->isRetainsAccessViaMembership());
	}

	public function testFindMarksRemoteAclAsNotRetainedViaMembership(): void {
		$board = new Board();
		$board->setId(14);
		$board->setOwner('bob');
		$board->setOwnerType(Acl::PERMISSION_TYPE_USER);

		$remoteAcl = new Acl();
		$remoteAcl->setId(901);
		$remoteAcl->setBoardId(14);
		$remoteAcl->setType(Acl::PERMISSION_TYPE_REMOTE);
		$remoteAcl->setParticipant('https://remote.example');
		$board->setAcl([$remoteAcl]);

		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 14, Acl::PERMISSION_READ, null, false);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(14, true, true, false)
			->willReturn($board);

		$this->boardMapper->expects($this->once())
			->method('mapOwner')
			->with($board);

		$this->boardMapper->expects($this->once())
			->method('mapAcl')
			->with($remoteAcl);

		$this->circlesService->expects($this->never())
			->method('isUserInCircle');

		$this->permissionService->expects($this->never())
			->method('userCan');

		$this->permissionService->expects($this->once())
			->method('matchPermissions')
			->with($board)
			->willReturn([
				Acl::PERMISSION_READ => true,
				Acl::PERMISSION_EDIT => true,
				Acl::PERMISSION_MANAGE => true,
				Acl::PERMISSION_SHARE => true,
				Board::PERMISSION_OWNER => false,
			]);

		$result = $this->service->find(14, false);
		$this->assertFalse($result->getAcl()[0]->isRetainsAccessViaMembership());
	}

	public function testTransferBoardOwnershipToNonExistentUserThrows(): void {
		$board = new Board();
		$board->setId(10);
		$board->setOwner('alice');
		$board->setOwnerType(Acl::PERMISSION_TYPE_USER);

		$this->connection->expects($this->once())->method('beginTransaction');
		$this->connection->expects($this->once())->method('rollBack');

		$this->boardMapper->expects($this->once())
			->method('find')
			->willReturn($board);

		$this->userManager->expects($this->once())
			->method('userExists')
			->with('ghost')
			->willReturn(false);

		$this->expectException(BadRequestException::class);
		$this->service->transferBoardOwnership(10, 'ghost', false, Acl::PERMISSION_TYPE_USER);
	}

	public function testTransferOwnershipUsesSourceOwnerTypeWhenFetchingBoards(): void {
		$this->userManager->expects($this->once())
			->method('userExists')
			->with('bob')
			->willReturn(true);

		$this->boardMapper->expects($this->once())
			->method('findAllByOwner')
			->with('circle-1', Acl::PERMISSION_TYPE_CIRCLE)
			->willReturn([]);

		$result = iterator_to_array($this->service->transferOwnership('circle-1', 'bob', false, Acl::PERMISSION_TYPE_USER, Acl::PERMISSION_TYPE_CIRCLE));
		$this->assertSame([], $result);
	}

	public function testTransferBoardOwnershipToNonExistentCircleThrows(): void {
		$board = new Board();
		$board->setId(10);
		$board->setOwner('alice');
		$board->setOwnerType(Acl::PERMISSION_TYPE_USER);

		$this->connection->expects($this->once())->method('beginTransaction');
		$this->connection->expects($this->once())->method('rollBack');

		$this->boardMapper->expects($this->once())
			->method('find')
			->willReturn($board);

		$this->circlesService->expects($this->once())
			->method('isCirclesEnabled')
			->willReturn(true);
		$this->circlesService->expects($this->once())
			->method('getCircle')
			->with('bad-circle')
			->willReturn(null);

		$this->expectException(BadRequestException::class);
		$this->service->transferBoardOwnership(10, 'bad-circle', false, Acl::PERMISSION_TYPE_CIRCLE);
	}
}
