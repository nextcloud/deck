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

if (!class_exists(\OCA\Circles\Model\Circle::class)) {
	require_once __DIR__ . '/../stubs/CirclesCircle.php';
}

use OC\Federation\CloudFederationFactory;
use OC\Federation\CloudFederationProviderManager;
use OC\Federation\CloudIdManager;
use OC\L10N\L10N;
use OC\Security\SecureRandom;
use OCA\Circles\Model\Circle;
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
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Session;
use OCA\Deck\Db\SessionMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Db\User;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\Event\BoardUpdatedEvent;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\BoardServiceValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
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

	/** @var LoggerInterface|MockObject */
	private $logger;

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
		$this->logger = $this->createMock(LoggerInterface::class);

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
			$this->logger,
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

	public function testCreateForTeamEmptyTeamId(): void {
		$this->expectException(BadRequestException::class);
		$this->service->createForTeam('Team board', $this->userId, '00ff00', '');
	}

	public function testCreateForTeamCirclesDisabled(): void {
		$this->circlesService->expects($this->once())
			->method('isCirclesEnabled')
			->willReturn(false);
		$this->expectException(BadRequestException::class);
		$this->service->createForTeam('Team board', $this->userId, '00ff00', 'team-a');
	}

	public function testCreateForTeamNotFound(): void {
		$this->circlesService->expects($this->once())
			->method('isCirclesEnabled')
			->willReturn(true);
		$this->circlesService->expects($this->once())
			->method('getCircle')
			->with('team-a')
			->willReturn(null);
		$this->expectException(BadRequestException::class);
		$this->service->createForTeam('Team board', $this->userId, '00ff00', 'team-a');
	}

	public function testCreateForTeamNotMember(): void {
		$this->circlesService->expects($this->once())
			->method('isCirclesEnabled')
			->willReturn(true);
		$this->circlesService->expects($this->once())
			->method('getCircle')
			->with('team-a')
			->willReturn($this->createMock(Circle::class));
		$this->circlesService->expects($this->once())
			->method('isUserInCircle')
			->with('team-a', $this->userId)
			->willReturn(false);
		$this->expectException(NoPermissionException::class);
		$this->service->createForTeam('Team board', $this->userId, '00ff00', 'team-a');
	}

	public function testCreateForTeamSuccess(): void {
		$createdBoard = new Board();
		$createdBoard->setId(42);
		$createdBoard->setTitle('Team board');
		$createdBoard->setOwner($this->userId);
		$createdBoard->setColor('00ff00');

		$updatedBoard = new Board();
		$updatedBoard->setId(42);
		$updatedBoard->setTitle('Team board');
		$updatedBoard->setOwner($this->userId);
		$updatedBoard->setColor('00ff00');
		$updatedBoard->setTeamId('team-a');

		$this->circlesService->expects($this->once())
			->method('isCirclesEnabled')
			->willReturn(true);
		$this->circlesService->expects($this->once())
			->method('getCircle')
			->with('team-a')
			->willReturn($this->createMock(Circle::class));
		$this->circlesService->expects($this->once())
			->method('isUserInCircle')
			->with('team-a', $this->userId)
			->willReturn(true);

		/** @var BoardService|MockObject $service */
		$service = $this->getMockBuilder(BoardService::class)
			->setConstructorArgs([
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
				$this->logger,
				$this->userId,
			])
			->onlyMethods(['create', 'addAcl', 'find'])
			->getMock();

		$service->expects($this->once())
			->method('create')
			->with('Team board', $this->userId, '00ff00')
			->willReturn($createdBoard);
		$this->boardMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (Board $board) {
				return $board->getId() === 42 && $board->getTeamId() === 'team-a';
			}))
			->willReturn($updatedBoard);
		$service->expects($this->once())
			->method('addAcl')
			->with(42, Acl::PERMISSION_TYPE_CIRCLE, 'team-a', true, false, false);
		$service->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($updatedBoard);

		$result = $service->createForTeam('Team board', $this->userId, '00ff00', 'team-a');
		$this->assertSame($updatedBoard, $result);
		$this->assertEquals('team-a', $result->getTeamId());
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
			->with($this->callback(function (Acl $actual) use ($acl) {
				return $actual->getBoardId() === $acl->getBoardId()
					&& $actual->getType() === $acl->getType()
					&& $actual->getParticipant() === $acl->getParticipant()
					&& $actual->getPermissionEdit() === $acl->getPermissionEdit()
					&& $actual->getPermissionShare() === $acl->getPermissionShare()
					&& $actual->getPermissionManage() === $acl->getPermissionManage()
					&& $actual->getCreatedAt() > 0
					&& $actual->getLastModifiedAt() > 0;
			}))
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
			->with($this->callback(function (Acl $actual) use ($acl) {
				return $actual->getBoardId() === $acl->getBoardId()
					&& $actual->getType() === $acl->getType()
					&& $actual->getParticipant() === $acl->getParticipant()
					&& $actual->getPermissionEdit() === $acl->getPermissionEdit()
					&& $actual->getPermissionShare() === $acl->getPermissionShare()
					&& $actual->getPermissionManage() === $acl->getPermissionManage()
					&& $actual->getCreatedAt() > 0
					&& $actual->getLastModifiedAt() > 0;
			}))
			->willReturn($acl);
		$this->eventDispatcher->expects(self::exactly(2))
			->method('dispatchTyped')
			->withConsecutive([$this->callback(function (AclCreatedEvent $event) use ($acl) {
				$eventAcl = $event->getAcl();
				return $eventAcl->getBoardId() === $acl->getBoardId()
					&& $eventAcl->getType() === $acl->getType()
					&& $eventAcl->getParticipant() === $acl->getParticipant()
					&& $eventAcl->getPermissionEdit() === $acl->getPermissionEdit()
					&& $eventAcl->getPermissionShare() === $acl->getPermissionShare()
					&& $eventAcl->getPermissionManage() === $acl->getPermissionManage()
					&& $eventAcl->getCreatedAt() > 0
					&& $eventAcl->getLastModifiedAt() > 0;
			})], [$this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'deck.board_share.created'
				&& $event->getParameters()['boardId'] === 123
				&& $event->getParameters()['sharedWith'] === 'admin'
				&& $event->getParameters()['shareType'] === 'user')]);
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

		$auditEvents = $this->collectAuditEvents();

		$result = $this->service->updateAcl(
			123, false, false, false
		);

		$this->assertCount(1, $auditEvents);
		$this->assertSame('deck.board_share.updated', $auditEvents[0]->getOperation());
		$this->assertSame('read', $auditEvents[0]->getParameters()['permissions']);
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
		$this->eventDispatcher->expects(self::exactly(2))
			->method('dispatchTyped')
			->withConsecutive([new AclDeletedEvent($acl)], [$this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'deck.board_share.deleted'
				&& $event->getLogMessage() === 'Share %s of board %s with %s "%s" was removed (permissions: %s)'
				&& $event->getParameters() === ['aclId' => null, 'boardId' => 123, 'shareType' => 'user', 'sharedWith' => 'admin', 'permissions' => 'read, edit, share, manage'])]);
		$this->assertEquals($acl, $this->service->deleteAcl(123));
	}

	public function testLeaveLogsCriticalAction(): void {
		$acl = new Acl();
		$acl->setId(9);
		$acl->setBoardId(123);
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$this->permissionService->method('userIsBoardOwner')->willReturn(false);
		$this->aclMapper->method('findParticipantFromBoard')->willReturn($acl);
		$this->aclMapper->method('delete')->willReturn($acl);
		$auditEvents = $this->collectAuditEvents();

		$this->service->leave(123);

		$this->assertCount(1, $auditEvents);
		$this->assertSame('deck.board_share.left', $auditEvents[0]->getOperation());
		$this->assertSame(['aclId' => 9, 'boardId' => 123, 'shareType' => 'user', 'sharedWith' => 'admin', 'permissions' => 'read, edit'], $auditEvents[0]->getParameters());
	}

	/**
	 * @return \ArrayObject<int, CriticalActionPerformedEvent>
	 */
	private function collectAuditEvents(): \ArrayObject {
		$auditEvents = new \ArrayObject();
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (Event $event) use ($auditEvents): void {
				if ($event instanceof CriticalActionPerformedEvent) {
					$auditEvents[] = $event;
				}
			});
		return $auditEvents;
	}

	public function testCreateLogsCriticalAction(): void {
		$board = new Board();
		$board->setId(5);
		$board->setOwner('admin');
		$this->boardMapper->method('insert')->willReturn($board);
		$this->permissionService->method('canCreate')->willReturn(true);
		$auditEvents = $this->collectAuditEvents();

		$this->service->create('MyBoard', 'admin', '00ff00');

		$this->assertCount(1, $auditEvents);
		$this->assertSame('deck.board.created', $auditEvents[0]->getOperation());
		$this->assertSame('Board %s of "%s" was created', $auditEvents[0]->getLogMessage());
		$this->assertSame(['boardId' => 5, 'owner' => 'admin'], $auditEvents[0]->getParameters());
	}

	public function testDeleteAndRestoreLogCriticalAction(): void {
		$board = new Board();
		$board->setId(42);
		$board->setOwner('admin');
		$board->setDeletedAt(0);
		$this->boardMapper->method('find')->willReturn($board);
		$this->boardMapper->method('update')->willReturnArgument(0);
		$this->permissionService->method('findUsers')->willReturn(['admin' => 'admin']);
		$this->sessionMapper->method('findAllActive')->willReturn([]);
		$this->aclMapper->method('findAll')->willReturn([]);
		$auditEvents = $this->collectAuditEvents();

		$this->service->delete(42);
		$this->service->deleteUndo(42);

		$this->assertSame(['deck.board.deleted', 'deck.board.restored'], array_map(static fn ($event) => $event->getOperation(), $auditEvents->getArrayCopy()));
		$this->assertSame(['boardId' => 42], $auditEvents[1]->getParameters());
	}

	public function testUpdateArchivedLogsCriticalAction(): void {
		$board = new Board();
		$board->setId(123);
		$board->setOwner('admin');
		$board->setArchived(false);
		$this->boardMapper->method('find')->willReturn($board);
		$this->boardMapper->method('update')->willReturnArgument(0);
		$this->permissionService->method('findUsers')->willReturn(['admin' => 'admin']);
		$this->sessionMapper->method('findAllActive')->willReturn([]);
		$auditEvents = $this->collectAuditEvents();

		$this->service->update(123, 'MyBoard', 'ffffff', true);
		$this->service->update(123, 'MyBoard', 'ffffff', true);

		$this->assertCount(1, $auditEvents);
		$this->assertSame('deck.board.archived', $auditEvents[0]->getOperation());
		$this->assertSame(['boardId' => 123], $auditEvents[0]->getParameters());
	}

	public function testCloneLogsCriticalAction(): void {
		$board = new Board();
		$board->setId(1);
		$board->setOwner('admin');
		$board->setTitle('Board');
		$board->setColor('ffffff');
		$this->permissionService->method('canCreate')->willReturn(true);
		$this->permissionService->method('findUsers')->willReturn(['admin' => 'admin']);
		$this->boardMapper->method('find')->willReturn($board);
		$this->boardMapper->method('insert')
			->willReturnCallback(static function (Board $newBoard): Board {
				$newBoard->setId(2);
				return $newBoard;
			});
		$this->aclMapper->method('findAll')->willReturn([]);
		$this->labelMapper->method('findAll')->willReturn([]);
		$this->stackMapper->method('findAll')->willReturn([]);
		$this->sessionMapper->method('findAllActive')->willReturn([]);
		$auditEvents = $this->collectAuditEvents();

		$this->service->clone(1, 'admin');

		$this->assertCount(1, $auditEvents);
		$this->assertSame('deck.board.cloned', $auditEvents[0]->getOperation());
		$this->assertSame(['sourceBoardId' => 1, 'boardId' => 2, 'owner' => 'admin'], $auditEvents[0]->getParameters());
	}

	public function testTransferBoardOwnershipLogsCriticalAction(): void {
		$board = new Board();
		$board->setId(7);
		$board->setOwner('admin');
		$this->boardMapper->method('find')->willReturn($board);
		$auditEvents = $this->collectAuditEvents();

		$this->service->transferBoardOwnership(7, 'newowner', true);

		$this->assertCount(1, $auditEvents);
		$this->assertSame('deck.board.ownership_transferred', $auditEvents[0]->getOperation());
		$this->assertSame('Ownership of board %s was transferred from "%s" to "%s"', $auditEvents[0]->getLogMessage());
		$this->assertSame(['boardId' => 7, 'previousOwner' => 'admin', 'newOwner' => 'newowner', 'changeContent' => true], $auditEvents[0]->getParameters());
	}

	public function testEnrichWithBoardSettingsReadsSwimlaneColumns(): void {
		// The three swimlane settings are columns on the board, shared by every
		// member, unlike notify-due and calendar, which are per user.
		$board = new Board();
		$board->setId(7);
		$board->setSwimlaneMode('labels');
		$board->setSwimlaneLabelOrder('[3,1]');
		$board->setSwimlaneUserOrder('["alice"]');

		$this->service->enrichWithBoardSettings($board);
		$settings = $board->getSettings();

		$this->assertSame('labels', $settings['swimlaneMode']);
		$this->assertSame('[3,1]', $settings['swimlaneLabelOrder']);
		$this->assertSame('["alice"]', $settings['swimlaneUserOrder']);
	}

	public function testEnrichWithBoardSettingsDefaultsSwimlaneToNone(): void {
		// Null columns, which is every board that never used the feature. It
		// has to default to the flat view rather than error.
		$board = new Board();
		$board->setId(8);

		$this->service->enrichWithBoardSettings($board);
		$settings = $board->getSettings();

		$this->assertSame('none', $settings['swimlaneMode']);
		$this->assertSame('[]', $settings['swimlaneLabelOrder']);
		$this->assertSame('[]', $settings['swimlaneUserOrder']);
	}

	public function testSwimlaneColumnsStayOutOfTheBoardJson(): void {
		// RelationalEntity serialises every protected property. The client
		// reads these three under 'settings', so a second copy at the top
		// level would change the shape of every board response.
		$board = new Board();
		$board->setId(9);
		$board->setSwimlaneMode('labels');
		$board->setSwimlaneLabelOrder('[1]');

		$json = $board->jsonSerialize();

		$this->assertArrayNotHasKey('swimlaneMode', $json);
		$this->assertArrayNotHasKey('swimlaneLabelOrder', $json);
		$this->assertArrayNotHasKey('swimlaneUserOrder', $json);
	}

	/**
	 * The three settings with values the client is allowed to send.
	 */
	public static function dataSwimlaneSettingAccepted(): array {
		return [
			'mode none' => ['swimlaneMode', 'none', 'none'],
			'mode labels' => ['swimlaneMode', 'labels', 'labels'],
			'mode assignees' => ['swimlaneMode', 'assignees', 'assignees'],
			'empty label order' => ['swimlaneLabelOrder', '[]', '[]'],
			'label ids' => ['swimlaneLabelOrder', '[3,1,2]', '[3,1,2]'],
			'catch-all lane' => ['swimlaneLabelOrder', '[3,"__none__"]', '[3,"__none__"]'],
			'label order with spaces is stored canonical' => ['swimlaneLabelOrder', '[ 2, 1 ]', '[2,1]'],
			'label id of a deleted label is dropped' => ['swimlaneLabelOrder', '[3,99,1]', '[3,1]'],
			'only unknown label ids' => ['swimlaneLabelOrder', '[98,99]', '[]'],
			'catch-all lane survives unknown label ids' => ['swimlaneLabelOrder', '[99,"__none__",2]', '["__none__",2]'],
			'user ids' => ['swimlaneUserOrder', '["0:admin","0:alice"]', '["0:admin","0:alice"]'],
			'user and group of the same id' => ['swimlaneUserOrder', '["0:admin","1:admin"]', '["0:admin","1:admin"]'],
			'user order with catch-all' => ['swimlaneUserOrder', '["0:alice","__none__"]', '["0:alice","__none__"]'],
			'user order with spaces is stored canonical' => ['swimlaneUserOrder', '[ "0:alice" , "__none__" ]', '["0:alice","__none__"]'],
			'group with slash stays unescaped' => ['swimlaneUserOrder', '["1:Sales/EU"]', '["1:Sales/EU"]'],
			'unicode escape is stored as the character' => ['swimlaneUserOrder', '["0:caf\u00e9"]', "[\"0:caf\u{e9}\"]"],
			'group with colon and quote' => ['swimlaneUserOrder', '["1:a:b\"c"]', '["1:a:b\"c"]'],
			'remote user with port' => ['swimlaneUserOrder', '["6:bob@cloud.example:8443"]', '["6:bob@cloud.example:8443"]'],
			'circle' => ['swimlaneUserOrder', '["7:' . str_repeat('c', 31) . '"]', '["7:' . str_repeat('c', 31) . '"]'],
			'ids differing only in case' => ['swimlaneUserOrder', '["0:Ann","0:ann"]', '["0:Ann","0:ann"]'],
			'participant of exactly 64 characters' => ['swimlaneUserOrder', '["0:' . str_repeat('a', 64) . '"]', '["0:' . str_repeat('a', 64) . '"]'],
			// 256 bytes, the 64 characters are what counts.
			'participant of 64 four-byte characters' => ['swimlaneUserOrder', '["0:' . str_repeat("\u{1F600}", 64) . '"]', '["0:' . str_repeat("\u{1F600}", 64) . '"]'],
			'participant that is the string zero' => ['swimlaneUserOrder', '["0:0"]', '["0:0"]'],
		];
	}

	/**
	 * The participant rule of the swimlane order is the one of the real validator.
	 */
	private function useRealValidator(): void {
		$this->boardServiceValidator->method('check')
			->willReturnCallback(function (array $data): void {
				(new BoardServiceValidator())->check($data);
			});
	}

	/**
	 * Entries in the format of earlier builds. The server drops them and
	 * stores the rest, so a saved order never holds a key the client
	 * cannot place.
	 */
	public static function dataSwimlaneSettingLegacyDropped(): array {
		return [
			'bare user id' => ['swimlaneUserOrder', '["admin"]', '[]'],
			'bare ids next to typed entries' => ['swimlaneUserOrder', '["admin","0:admin","2:x","00:x","+1:x"]', '["0:admin"]'],
			'catch-all lane survives' => ['swimlaneUserOrder', '["alice","__none__"]', '["__none__"]'],
			'label as a string' => ['swimlaneLabelOrder', '["label-1",3,"12"]', '[3]'],
			'label id as a numeric string' => ['swimlaneLabelOrder', '["12"]', '[]'],
			// The string is dropped, so it is no duplicate of the integer 3.
			'numeric string next to the same integer' => ['swimlaneLabelOrder', '["3",3]', '[3]'],
			'user entry in a label order' => ['swimlaneLabelOrder', '["0:admin"]', '[]'],
			'empty string in a label order' => ['swimlaneLabelOrder', '["",1]', '[1]'],
			'empty string in a user order' => ['swimlaneUserOrder', '["","0:a"]', '["0:a"]'],
			// Duplicates are counted after the drop, so two dropped copies are no duplicate.
			'dropped duplicate' => ['swimlaneUserOrder', '["a","a","0:a"]', '["0:a"]'],
		];
	}

	/**
	 * @dataProvider dataSwimlaneSettingAccepted
	 * @dataProvider dataSwimlaneSettingLegacyDropped
	 */
	public function testSetSwimlaneSettingWritesTheColumn(string $key, string $value, string $stored): void {
		$this->useRealValidator();
		$board = new Board();
		$board->setId(5);
		if ($key === 'swimlaneLabelOrder') {
			// The board has the labels 1 to 3, other ids are gone.
			$labels = [];
			foreach ([1, 2, 3] as $id) {
				$label = new Label();
				$label->setId($id);
				$labels[] = $label;
			}
			$this->labelMapper->expects($this->once())
				->method('findAll')
				->with(5)
				->willReturn($labels);
		} else {
			$this->labelMapper->expects($this->never())->method('findAll');
		}
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(5)
			->willReturn($board);
		// QBMapper writes only the fields a setter marked as updated, so the
		// magic setter has to have run for this to persist anything at all.
		$this->boardMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (Board $written) use ($key, $stored) {
				// Entity::markFieldUpdated() keys the array by field name.
				$this->assertArrayHasKey($key, $written->getUpdatedFields());
				$this->assertSame($stored, $written->{'get' . ucfirst($key)}());
				return true;
			}))
			->willReturn($board);
		// Shared board state: the ETag has to move, or REST clients keep a stale 304.
		$this->changeHelper->expects($this->once())
			->method('boardChanged')
			->with(5);
		// Other open tabs only learn about the change through this event.
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function ($event) {
				$this->assertInstanceOf(BoardUpdatedEvent::class, $event);
				$this->assertSame(5, $event->getBoardId());
				return true;
			}));

		// The answer is what is stored, the client takes it as the new order.
		$this->assertSame($stored, $this->service->setSwimlaneSetting(5, $key, $value));
	}

	/**
	 * Values the client must not get past, because every board member is
	 * served them on board load.
	 */
	public static function dataSwimlaneSettingRejected(): array {
		// 1500 entries of 49 bytes are 73.5 kB, over twice the limit of 32768 bytes.
		$twiceTheLimit = [];
		for ($i = 0; $i < 1500; $i++) {
			$twiceTheLimit[] = '0:' . str_pad((string)$i, 44, 'a');
		}
		// 2001 short entries, over twice the entry limit and well under the byte limit.
		$twiceTheEntries = [];
		for ($i = 0; $i <= 2000; $i++) {
			$twiceTheEntries[] = '0:u' . $i;
		}

		// One byte over twice the limit, padded with the whitespace JSON ignores.
		$oneByteOver = str_pad('["0:a"]', 65537, ' ');

		return [
			'unknown mode' => ['swimlaneMode', 'sideways'],
			'mode as number' => ['swimlaneMode', 1],
			'order not json' => ['swimlaneLabelOrder', 'nope'],
			'order is an object' => ['swimlaneLabelOrder', '{"a":1}'],
			'order holds an object' => ['swimlaneLabelOrder', '[{"id":1}]'],
			'order holds null' => ['swimlaneLabelOrder', '[1,null]'],
			'label id zero' => ['swimlaneLabelOrder', '[0]'],
			'label id negative' => ['swimlaneLabelOrder', '[-1]'],
			'label id as a float' => ['swimlaneLabelOrder', '[1.5]'],
			'label order with a duplicate' => ['swimlaneLabelOrder', '[3,3]'],
			'label order with a nested array' => ['swimlaneLabelOrder', '[[3]]'],
			'order is a scalar' => ['swimlaneLabelOrder', '1'],
			'user entry with an empty participant' => ['swimlaneUserOrder', '["0:"]'],
			'user entry as a number' => ['swimlaneUserOrder', '[0]'],
			'user entry with a duplicate' => ['swimlaneUserOrder', '["0:a","0:a"]'],
			'user order with a duplicate catch-all' => ['swimlaneUserOrder', '["__none__","__none__"]'],
			'user order with a nested array' => ['swimlaneUserOrder', '[["0:a"]]'],
			'participant of 65 characters' => ['swimlaneUserOrder', '["0:' . str_repeat('a', 65) . '"]'],
			'participant of 65 four-byte characters' => ['swimlaneUserOrder', '["0:' . str_repeat("\u{1F600}", 65) . '"]'],
			'user order over twice the byte limit' => ['swimlaneUserOrder', json_encode($twiceTheLimit)],
			'user order over twice the entry limit' => ['swimlaneUserOrder', json_encode($twiceTheEntries)],
			'user order of 65537 bytes' => ['swimlaneUserOrder', $oneByteOver],
			// Only a user order may arrive over the limit, a label order is refused as it is.
			'label order too many entries' => ['swimlaneLabelOrder', '[' . implode(',', range(1, 1001)) . ']'],
		];
	}

	/**
	 * @dataProvider dataSwimlaneSettingRejected
	 */
	public function testSetSwimlaneSettingRejectsMalformedValue(string $key, mixed $value): void {
		$this->useRealValidator();
		$this->boardMapper->expects($this->never())->method('update');
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		// Refused as sent, so no participant is looked up and nothing is logged.
		$this->assignedUsersMapper->expects($this->never())->method('findParticipantsOnBoard');
		$this->logger->expects($this->never())->method('warning');
		$this->logger->expects($this->never())->method('info');
		// A real board, so a value that only fails after find is rejected on purpose.
		$board = new Board();
		$board->setId(5);
		$board->setArchived(false);
		$this->boardMapper->method('find')->willReturn($board);

		$this->expectException(BadRequestException::class);
		$this->service->setSwimlaneSetting(5, $key, $value);
	}

	/**
	 * The board a full user order is saved on. Its owner is `owner`.
	 */
	private function boardForTheValve(): Board {
		$board = new Board();
		$board->setId(5);
		$board->setOwner('owner');
		$board->setArchived(false);
		$this->boardMapper->method('find')->willReturn($board);
		$this->boardMapper->method('update')->willReturn($board);
		return $board;
	}

	/**
	 * Filler entries that belong to nobody on the board, 62 characters each,
	 * so 500 of them are 33.5 kB and fill the order alone.
	 *
	 * @return list<string>
	 */
	private static function unrelatedEntries(int $count): array {
		$entries = [];
		for ($i = 0; $i < $count; $i++) {
			$entries[] = '0:' . str_pad('filler' . $i, 62, 'x');
		}
		return $entries;
	}

	/**
	 * Names the participants of the board by entry, in the type:participant form.
	 *
	 * @param list<string> $entries
	 */
	private function assignParticipants(array $entries): void {
		$rows = [];
		foreach ($entries as $entry) {
			[$type, $participant] = explode(':', $entry, 2);
			$rows[] = ['type' => (int)$type, 'participant' => $participant];
		}
		$this->assignedUsersMapper->method('findParticipantsOnBoard')->with(5)->willReturn($rows);
	}

	private function aclEntry(int $type, string $participant): Acl {
		$acl = new Acl();
		$acl->setType($type);
		$acl->setParticipant($participant);
		return $acl;
	}

	public function testSetSwimlaneSettingDropsUnrelatedEntriesFromAFullUserOrder(): void {
		$this->useRealValidator();
		$this->boardForTheValve();
		// Between the limit and twice the limit, so only the valve lets it pass.
		$fillers = self::unrelatedEntries(500);
		$order = array_merge(
			array_slice($fillers, 0, 100),
			['0:owner', '__none__'],
			array_slice($fillers, 100, 100),
			// Shared with a group, nobody is assigned from it. The team is assigned.
			['1:Sales/EU', '7:abc123'],
			array_slice($fillers, 200, 100),
			// The assignment says Admin, the client builds the lane from the user's own id.
			['0:admin', '6:bob@cloud.example:8443'],
			array_slice($fillers, 300, 100),
			// Same name as an assigned user but another type, and a user who is not on the board.
			// Carol is shared as Carol and sits in the order as carol.
			['1:ann', '0:stranger', '0:carol', '0:ann'],
			array_slice($fillers, 400, 100),
		);
		$value = json_encode($order);
		$this->assertGreaterThan(32768, strlen($value));
		$this->assertLessThan(65536, strlen($value));

		// The rows come in another order than the lanes.
		$this->assignParticipants(['6:bob@cloud.example:8443', '0:ann', '0:Admin', '7:abc123']);
		$this->aclMapper->method('findAll')->with(5)->willReturn([
			$this->aclEntry(Acl::PERMISSION_TYPE_GROUP, 'Sales/EU'),
			$this->aclEntry(Acl::PERMISSION_TYPE_USER, 'Carol'),
		]);
		$ownSpelling = ['Admin' => 'admin', 'Carol' => 'carol'];
		$this->userManager->method('get')->willReturnCallback(function (string $uid) use ($ownSpelling): ?IUser {
			if (!isset($ownSpelling[$uid])) {
				return null;
			}
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($ownSpelling[$uid]);
			return $user;
		});
		$kept = '["0:owner","__none__","1:Sales/EU","7:abc123","0:admin","6:bob@cloud.example:8443","0:carol","0:ann"]';
		$this->logger->expects($this->never())->method('warning');
		$this->logger->expects($this->once())
			->method('info')
			->with(
				'Swimlane user order of board 5 was over its limit, unrelated entries were dropped',
				$this->callback(function (array $context) use ($order, $kept) {
					$this->assertEqualsCanonicalizing(['boardId', 'removed', 'bytesBefore', 'entriesBefore', 'bytesAfter', 'entriesAfter', 'maxBytes', 'maxEntries'], array_keys($context));
					$this->assertSame(5, $context['boardId']);
					$this->assertSame(500 + 10 - 8, $context['removed']);
					// The stored encoding does not escape the slash of the group.
					$this->assertSame(strlen(json_encode($order, JSON_UNESCAPED_SLASHES)), $context['bytesBefore']);
					$this->assertSame(510, $context['entriesBefore']);
					$this->assertSame(strlen($kept), $context['bytesAfter']);
					$this->assertSame(8, $context['entriesAfter']);
					$this->assertSame(32768, $context['maxBytes']);
					$this->assertSame(1000, $context['maxEntries']);
					return true;
				})
			);

		$this->assertSame($kept, $this->service->setSwimlaneSetting(5, 'swimlaneUserOrder', $value));
	}

	public function testSetSwimlaneSettingDropsUnrelatedEntriesOverTheEntryLimit(): void {
		$this->useRealValidator();
		$this->boardForTheValve();
		// 1200 short entries are 14 kB, over the entry limit only.
		$order = [];
		for ($i = 0; $i < 1200; $i++) {
			$order[] = '0:u' . $i;
		}
		$this->assignParticipants(['0:u7', '0:u1100']);
		$this->aclMapper->method('findAll')->willReturn([]);
		$this->logger->expects($this->once())->method('info');
		$this->logger->expects($this->never())->method('warning');

		$this->assertSame('["0:u7","0:u1100"]', $this->service->setSwimlaneSetting(5, 'swimlaneUserOrder', json_encode($order)));
	}

	/**
	 * The input limit is twice the stored one and includes the limit itself.
	 */
	public static function dataValveInputBoundary(): array {
		// Exactly 2000 entries, one of them belongs to the board.
		$entries = [];
		for ($i = 0; $i < 2000; $i++) {
			$entries[] = '0:u' . $i;
		}
		$twoThousand = json_encode($entries);

		// Exactly 65536 bytes, padded with the whitespace JSON ignores.
		$fillers = array_merge(['0:owner'], self::unrelatedEntries(970));
		$exactBytes = str_pad(json_encode($fillers), 65536, ' ');
		self::assertSame(65536, strlen($exactBytes));
		self::assertSame(2000, count($entries));

		return [
			'2000 entries' => [$twoThousand, ['0:u5'], '["0:u5"]'],
			'65536 bytes' => [$exactBytes, ['0:owner'], '["0:owner"]'],
		];
	}

	/**
	 * @dataProvider dataValveInputBoundary
	 * @param list<string> $related
	 */
	public function testSetSwimlaneSettingTakesAUserOrderOfExactlyTwiceTheLimit(string $value, array $related, string $stored): void {
		$this->useRealValidator();
		$this->boardForTheValve();
		$this->assignParticipants($related);
		$this->aclMapper->method('findAll')->willReturn([]);
		$this->logger->expects($this->once())->method('info');
		$this->logger->expects($this->never())->method('warning');

		$this->assertSame($stored, $this->service->setSwimlaneSetting(5, 'swimlaneUserOrder', $value));
	}

	public function testSetSwimlaneSettingKeepsUnrelatedEntriesWhileTheOrderFits(): void {
		$this->useRealValidator();
		$this->boardForTheValve();
		// Nothing is looked up and nothing is dropped when the order fits.
		$this->assignedUsersMapper->expects($this->never())->method('findParticipantsOnBoard');
		$this->aclMapper->expects($this->never())->method('findAll');
		$this->userManager->expects($this->never())->method('get');
		$order = ['0:stranger', '1:nobody', '__none__'];

		$this->assertSame(json_encode($order), $this->service->setSwimlaneSetting(5, 'swimlaneUserOrder', json_encode($order)));
	}

	/**
	 * Orders that the valve cannot save, because the entries that belong to
	 * the board are over the limit on their own.
	 */
	public static function dataSwimlaneUserOrderStaysTooLarge(): array {
		// 600 distinct entries, 62 characters each, 40.2 kB: over the byte limit and below 1000 entries.
		$longIds = [];
		for ($i = 0; $i < 600; $i++) {
			$longIds[] = '0:' . str_pad((string)$i, 62, 'a');
		}
		// 120 distinct entries, each with 64 characters. Each U+2028 is 3 bytes raw
		// and 6 bytes as the escape json_encode writes, so the vector is 22.9 kB raw
		// and the stored string would be 44.9 kB, over the limit. The flag keeps the
		// character raw in the vector.
		$lineSeparators = [];
		for ($i = 0; $i < 120; $i++) {
			$lineSeparators[] = '0:' . str_repeat("\u{2028}", 61) . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
		}
		$tooMany = [];
		for ($i = 0; $i < 1001; $i++) {
			$tooMany[] = '0:u' . $i;
		}
		return [
			'40 kB, all on the board' => [json_encode($longIds)],
			'22.9 kB that grows past the limit when encoded, all on the board' => [json_encode($lineSeparators, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS)],
			'1001 entries, all on the board' => [json_encode($tooMany)],
		];
	}

	/**
	 * @dataProvider dataSwimlaneUserOrderStaysTooLarge
	 */
	public function testSetSwimlaneSettingRefusesAUserOrderThatStaysTooLarge(string $value): void {
		$this->useRealValidator();
		$this->boardForTheValve();
		// Every entry of the order is a participant of the board.
		$this->assignParticipants(json_decode($value, true));
		$this->aclMapper->method('findAll')->willReturn([]);
		$this->boardMapper->expects($this->never())->method('update');
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->logger->expects($this->never())->method('info');
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Swimlane user order of board 5 is still over its limit after dropping unrelated entries',
				$this->callback(function (array $context) {
					$this->assertEqualsCanonicalizing(['boardId', 'removed', 'bytesBefore', 'entriesBefore', 'bytesAfter', 'entriesAfter', 'maxBytes', 'maxEntries'], array_keys($context));
					$this->assertSame(0, $context['removed']);
					return true;
				})
			);

		$this->expectException(BadRequestException::class);
		$this->service->setSwimlaneSetting(5, 'swimlaneUserOrder', $value);
	}

	public function testSetSwimlaneSettingRefusesAnOverlongLabelOrderBeforeTheValve(): void {
		$this->useRealValidator();
		$this->boardForTheValve();
		// The label list has no 2x input allowance, so 1001 entries stop in the
		// validation. This makes sure a label order never runs into the valve,
		// which would drop every label id because none of them is a participant.
		// All 1000 ids are labels of the board and the catch-all makes it 1001 entries.
		$ids = range(1, 1000);
		$labels = [];
		foreach ($ids as $id) {
			$label = new Label();
			$label->setId($id);
			$labels[] = $label;
		}
		$this->labelMapper->method('findAll')->willReturn($labels);
		$this->assignedUsersMapper->expects($this->never())->method('findParticipantsOnBoard');
		$this->boardMapper->expects($this->never())->method('update');

		$this->expectException(BadRequestException::class);
		$this->service->setSwimlaneSetting(5, 'swimlaneLabelOrder', json_encode(array_merge($ids, ['__none__'])));
	}

	/**
	 * Shared with the client, see laneKeysForCard.spec.js.
	 */
	public static function dataSwimlaneAssigneeKey(): array {
		return [
			'user' => [0, 'admin', '0:admin'],
			'group with slash' => [1, 'Sales/EU', '1:Sales/EU'],
			'remote user with port' => [6, 'bob@cloud.example:8443', '6:bob@cloud.example:8443'],
			'group with colon and quote' => [1, 'a:b"c', '1:a:b"c'],
		];
	}

	/**
	 * @dataProvider dataSwimlaneAssigneeKey
	 */
	public function testSwimlaneAssigneeKey(int $type, string $participant, string $expected): void {
		$this->assertSame($expected, BoardService::swimlaneAssigneeKey($type, $participant));
	}

	private static function userOrder(int $entries, int $idLength = 3): string {
		$order = [];
		for ($i = 0; $i < $entries; $i++) {
			$order[] = '0:' . str_pad((string)$i, $idLength, 'u');
		}
		return json_encode($order);
	}

	private static function labelOrder(int $entries): string {
		return json_encode(range(1, $entries));
	}

	public static function dataSwimlaneOrderWarning(): array {
		return [
			'790 to 800 entries' => [self::userOrder(790), self::userOrder(800), true],
			'null to 800 entries' => [null, self::userOrder(800), true],
			'800 to 810 entries' => [self::userOrder(800), self::userOrder(810), false],
			'810 to 700 entries' => [self::userOrder(810), self::userOrder(700), false],
			// 900 entries are at 90 percent already, the bytes rise from 69 to 85 percent.
			'bytes cross, entries were over before' => [self::userOrder(900, 20), self::userOrder(900, 26), false],
			// 400 entries of 64 characters are 26.8 kB, 82 percent of the bytes and 40 percent of the entries.
			'bytes alone cross' => [self::userOrder(400, 8), self::userOrder(400, 62), true],
			'old value is not json' => ['nope', self::userOrder(800), true],
			'old value is a number' => ['5', self::userOrder(800), true],
			// The label branch has to read the label column. Only the 800 to 810 vector catches a wrong getter, because the user column is empty and 790 to 800 warns either way.
			'label order, 790 to 800 entries' => [self::labelOrder(790), self::labelOrder(800), true, 'swimlaneLabelOrder'],
			'label order, 800 to 810 entries' => [self::labelOrder(800), self::labelOrder(810), false, 'swimlaneLabelOrder'],
		];
	}

	/**
	 * @dataProvider dataSwimlaneOrderWarning
	 */
	public function testSetSwimlaneSettingWarnsWhenTheOrderNearsItsLimit(?string $previous, string $value, bool $warns, string $key = 'swimlaneUserOrder'): void {
		$this->useRealValidator();
		$board = new Board();
		$board->setId(5);
		if ($key === 'swimlaneLabelOrder') {
			$board->setSwimlaneLabelOrder($previous);
			// Every id of the vector is a label of the board, so none is dropped.
			$labels = [];
			foreach (json_decode($value, true) as $id) {
				$label = new Label();
				$label->setId($id);
				$labels[] = $label;
			}
			$this->labelMapper->method('findAll')->willReturn($labels);
		} else {
			$board->setSwimlaneUserOrder($previous);
		}
		$this->boardMapper->method('find')->willReturn($board);
		$this->boardMapper->method('update')->willReturn($board);
		if ($warns) {
			$entries = count(json_decode($value, true));
			$this->logger->expects($this->once())
				->method('warning')
				->with(
					'Swimlane order of board 5 passed 80% of its limit',
					$this->callback(function (array $context) use ($value, $entries, $key) {
						// Only ids and numbers, no entry and no user.
						$this->assertEqualsCanonicalizing(['boardId', 'setting', 'bytes', 'entries', 'maxBytes', 'maxEntries'], array_keys($context));
						$this->assertSame(5, $context['boardId']);
						$this->assertSame($key, $context['setting']);
						$this->assertSame(strlen($value), $context['bytes']);
						$this->assertSame($entries, $context['entries']);
						$this->assertSame(32768, $context['maxBytes']);
						$this->assertSame(1000, $context['maxEntries']);
						return true;
					})
				);
		} else {
			$this->logger->expects($this->never())->method('warning');
		}

		$this->service->setSwimlaneSetting(5, $key, $value);
	}

	public function testSetSwimlaneSettingNamesTheParticipantLimit(): void {
		$this->useRealValidator();
		$this->boardMapper->expects($this->never())->method('update');

		$this->expectException(BadRequestException::class);
		// Upstream text, it ends with a space.
		$this->expectExceptionMessage('participant cannot be longer than 64 characters');
		$this->service->setSwimlaneSetting(5, 'swimlaneUserOrder', '["0:' . str_repeat('a', 65) . '"]');
	}

	public function testSetSwimlaneSettingRejectsAnotherSetting(): void {
		// The controller routes by the same list, so a key that is not a
		// swimlane setting must never reach a board column.
		$this->boardMapper->expects($this->never())->method('update');
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->expectException(BadRequestException::class);
		$this->service->setSwimlaneSetting(5, 'notify-due', 'all');
	}

	public function testSetSwimlaneSettingRejectsArchivedBoard(): void {
		$board = new Board();
		$board->setId(5);
		$board->setArchived(true);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(5)
			->willReturn($board);
		$this->boardMapper->expects($this->never())->method('update');
		$this->changeHelper->expects($this->never())->method('boardChanged');
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->expectException(StatusException::class);
		$this->service->setSwimlaneSetting(5, 'swimlaneMode', 'labels');
	}

	private function boardWithSwimlaneOrders(int $id, ?string $userOrder, ?string $labelOrder = null): Board {
		$board = new Board();
		$board->setId($id);
		$board->setSwimlaneUserOrder($userOrder);
		$board->setSwimlaneLabelOrder($labelOrder);
		return $board;
	}

	/**
	 * Lets the mapper serve the given boards. A value that is a Throwable is
	 * thrown instead. Every call of flushCache and find lands in $calls.
	 *
	 * @param array<int, Board|\Throwable> $boards
	 * @param list<string> $calls
	 */
	private function serveSwimlaneBoards(array $boards, array &$calls): void {
		$this->boardMapper->method('flushCache')
			->willReturnCallback(function (?int $boardId = null) use (&$calls): void {
				$calls[] = 'flush ' . $boardId;
			});
		$this->boardMapper->method('find')
			->willReturnCallback(function (int $id, bool $withLabels = false, bool $withAcl = false, bool $allowDeleted = false) use ($boards, &$calls): Board {
				$calls[] = 'find ' . $id . ($allowDeleted ? ' withDeleted' : '');
				if ($boards[$id] instanceof \Throwable) {
					throw $boards[$id];
				}
				return $boards[$id];
			});
	}

	public function testRemoveSwimlaneUserKeyDropsOnlyTheExactEntry(): void {
		$board = $this->boardWithSwimlaneOrders(5, '["0:ann","1:ann","__none__","0:Ann"]', '[2,"__none__"]');
		$calls = [];
		$this->serveSwimlaneBoards([5 => $board], $calls);
		$this->boardMapper->expects($this->once())
			->method('findIdsWithSwimlaneUserOrderEntry')
			->with('"0:ann"')
			->willReturn([5]);
		$this->boardMapper->expects($this->once())->method('update')->with($board);
		$this->changeHelper->expects($this->once())->method('boardChanged')->with(5);
		$this->eventDispatcher->expects($this->once())->method('dispatchTyped');
		$this->labelMapper->expects($this->never())->method('findAll');

		$this->service->removeSwimlaneUserKey(Acl::PERMISSION_TYPE_USER, 'ann');

		$this->assertSame('["1:ann","__none__","0:Ann"]', $board->getSwimlaneUserOrder());
		// The label order is not touched.
		$this->assertSame('[2,"__none__"]', $board->getSwimlaneLabelOrder());
		// The cache is dropped before the read, deleted boards count too.
		$this->assertSame(['flush 5', 'find 5 withDeleted'], $calls);
	}

	public static function dataSwimlaneSearchNeedle(): array {
		return [
			'plain id' => [Acl::PERMISSION_TYPE_USER, 'ann', '"0:ann"'],
			// The stored text does not escape a slash, the search must not either.
			'slash' => [Acl::PERMISSION_TYPE_GROUP, 'Sales/EU', '"1:Sales/EU"'],
			'non-ascii' => [Acl::PERMISSION_TYPE_USER, 'Zoë', '"0:Zoë"'],
			'quote' => [Acl::PERMISSION_TYPE_CIRCLE, 'a"b', '"7:a\"b"'],
		];
	}

	/**
	 * @dataProvider dataSwimlaneSearchNeedle
	 */
	public function testRemoveSwimlaneUserKeySearchesTheStoredEncoding(int $type, string $participant, string $needle): void {
		$this->boardMapper->expects($this->once())
			->method('findIdsWithSwimlaneUserOrderEntry')
			->with($needle)
			->willReturn([]);

		$this->service->removeSwimlaneUserKey($type, $participant);
	}

	public function testRemoveSwimlaneUserKeyWritesNothingWhenTheEntryIsOnlyPartOfAnother(): void {
		// The group id contains the text of the user entry, so the search
		// finds this board although the list has no such entry.
		$board = $this->boardWithSwimlaneOrders(5, '["1:x\"0:ann","__none__"]');
		$calls = [];
		$this->serveSwimlaneBoards([5 => $board], $calls);
		$this->boardMapper->method('findIdsWithSwimlaneUserOrderEntry')->willReturn([5]);
		$this->boardMapper->expects($this->never())->method('update');
		$this->changeHelper->expects($this->never())->method('boardChanged');
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->removeSwimlaneUserKey(Acl::PERMISSION_TYPE_USER, 'ann');

		$this->assertSame('["1:x\"0:ann","__none__"]', $board->getSwimlaneUserOrder());
	}

	public function testRemoveSwimlaneUserKeySkipsABoardWithoutAnOrder(): void {
		$calls = [];
		$this->serveSwimlaneBoards([5 => $this->boardWithSwimlaneOrders(5, null)], $calls);
		$this->boardMapper->method('findIdsWithSwimlaneUserOrderEntry')->willReturn([5]);
		$this->boardMapper->expects($this->never())->method('update');
		// Skipped, a missing order is no failure.
		$this->logger->expects($this->never())->method('warning');

		$this->service->removeSwimlaneUserKey(Acl::PERMISSION_TYPE_USER, 'ann');
	}

	public function testRemoveSwimlaneUserKeySkipsABoardThatIsGone(): void {
		$other = $this->boardWithSwimlaneOrders(6, '["0:ann"]');
		$calls = [];
		$this->serveSwimlaneBoards([5 => new DoesNotExistException('gone'), 6 => $other], $calls);
		$this->boardMapper->method('findIdsWithSwimlaneUserOrderEntry')->willReturn([5, 6]);
		// A board deleted in between is no reason to log.
		$this->logger->expects($this->never())->method('warning');
		$this->boardMapper->expects($this->once())->method('update')->with($other);

		$this->service->removeSwimlaneUserKey(Acl::PERMISSION_TYPE_USER, 'ann');

		$this->assertSame('[]', $other->getSwimlaneUserOrder());
	}

	public function testRemoveSwimlaneUserKeyLogsAFailingBoardAndGoesOn(): void {
		$failure = new \RuntimeException('database is gone');
		$other = $this->boardWithSwimlaneOrders(6, '["0:ann"]');
		$calls = [];
		$this->serveSwimlaneBoards([5 => $failure, 6 => $other], $calls);
		$this->boardMapper->method('findIdsWithSwimlaneUserOrderEntry')->willReturn([5, 6]);
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				$this->callback(function (string $message): bool {
					// The board is named, the deleted participant is not.
					$this->assertStringContainsString('board 5', $message);
					$this->assertStringNotContainsString('ann', $message);
					return true;
				}),
				$this->callback(function (array $context) use ($failure): bool {
					$this->assertEqualsCanonicalizing(['boardId', 'exception'], array_keys($context));
					$this->assertSame(5, $context['boardId']);
					$this->assertSame($failure, $context['exception']);
					return true;
				})
			);
		$this->boardMapper->expects($this->once())->method('update')->with($other);

		// Nothing reaches the caller, the user deletion goes on.
		$this->service->removeSwimlaneUserKey(Acl::PERMISSION_TYPE_USER, 'ann');

		$this->assertSame('[]', $other->getSwimlaneUserOrder());
	}
}
