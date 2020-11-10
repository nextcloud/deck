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

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\NotFoundException;
use OCA\Deck\Notification\NotificationHelper;
use OCP\Activity\IEvent;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AssignmentServiceTest extends TestCase {


	/**
	 * @var MockObject|PermissionService
	 */
	private $permissionService;
	/**
	 * @var MockObject|CardMapper
	 */
	private $cardMapper;
	/**
	 * @var MockObject|AssignmentMapper
	 */
	private $assignedUsersMapper;
	/**
	 * @var MockObject|AclMapper
	 */
	private $aclMapper;
	/**
	 * @var MockObject|NotificationHelper
	 */
	private $notificationHelper;
	/**
	 * @var MockObject|ChangeHelper
	 */
	private $changeHelper;
	/**
	 * @var MockObject|ActivityManager
	 */
	private $activityManager;
	/**
	 * @var MockObject|IEventDispatcher
	 */
	private $eventDispatcher;
	/**
	 * @var AssignmentService
	 */
	private $assignmentService;

	public function setUp(): void {
		parent::setUp();
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->notificationHelper = $this->createMock(NotificationHelper::class);
		$this->assignedUsersMapper = $this->createMock(AssignmentMapper::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);
		$this->assignmentService = new AssignmentService(
			$this->permissionService,
			$this->cardMapper,
			$this->assignedUsersMapper,
			$this->aclMapper,
			$this->notificationHelper,
			$this->activityManager,
			$this->changeHelper,
			$this->eventDispatcher,
			'admin'
		);
	}

	public function mockActivity($type, $object, $subject) {
		// ActivityManager::DECK_OBJECT_BOARD, $newAcl, ActivityManager::SUBJECT_BOARD_SHARE
		$event = $this->createMock(IEvent::class);
		$this->activityManager->expects($this->once())
			->method('createEvent')
			->with($type, $object, $subject)
			->willReturn($event);
		$this->activityManager->expects($this->once())
			->method('sendToUsers')
			->with($event);
	}

	public function testAssignUser() {
		$assignments = [];
		$this->assignedUsersMapper->expects($this->once())
			->method('findAll')
			->with(123)
			->willReturn($assignments);
		$assignment = new Assignment();
		$assignment->setCardId(123);
		$assignment->setParticipant('admin');
		$assignment->setType(Assignment::TYPE_USER);
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->willReturn(1);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->with(1)
			->willReturn(['admin' => 'admin', 'user1' => 'user1']);
		$this->aclMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);
		$this->assignedUsersMapper->expects($this->once())
			->method('insert')
			->with($assignment)
			->willReturn($assignment);
		$actual = $this->assignmentService->assignUser(123, 'admin');
		$this->assertEquals($assignment, $actual);
	}

	public function testAssignUserNoParticipant() {
		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage('The user is not part of the board');
		$assignments = [];
		$this->assignedUsersMapper->expects($this->once())
			->method('findAll')
			->with(123)
			->willReturn($assignments);
		$assignment = new Assignment();
		$assignment->setCardId(123);
		$assignment->setParticipant('admin');
		$assignment->setType(Assignment::TYPE_USER);
		$this->cardMapper->expects($this->once())
			->method('findBoardId')
			->willReturn(1);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->with(1)
			->willReturn(['user2' => 'user2', 'user1' => 'user1']);
		$this->aclMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);
		$actual = $this->assignmentService->assignUser(123, 'admin');
	}

	public function testAssignUserExisting() {
		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage('The user is already assigned to the card');
		$assignment = new Assignment();
		$assignment->setCardId(123);
		$assignment->setParticipant('admin');
		$assignment->setType(Assignment::TYPE_USER);
		$assignments = [
			$assignment
		];
		$this->assignedUsersMapper->expects($this->once())
			->method('findAll')
			->with(123)
			->willReturn($assignments);
		$actual = $this->assignmentService->assignUser(123, 'admin');
		$this->assertFalse($actual);
	}

	public function testUnassignUserExisting() {
		$assignment = new Assignment();
		$assignment->setCardId(123);
		$assignment->setParticipant('admin');
		$assignment->setType(Assignment::TYPE_USER);
		$assignments = [
			$assignment
		];
		$this->assignedUsersMapper->expects($this->once())
			->method('findAll')
			->with(123)
			->willReturn($assignments);
		$this->assignedUsersMapper->expects($this->once())
			->method('delete')
			->with($assignment)
			->willReturn($assignment);
		$actual = $this->assignmentService->unassignUser(123, 'admin');
		$this->assertEquals($assignment, $actual);
	}

	public function testUnassignUserNotExisting() {
		$this->expectException(NotFoundException::class);
		$assignment = new Assignment();
		$assignment->setCardId(123);
		$assignment->setParticipant('admin');
		$assignment->setType(Assignment::TYPE_USER);
		$assignments = [
			$assignment
		];
		$this->assignedUsersMapper->expects($this->once())
			->method('findAll')
			->with(123)
			->willReturn($assignments);
		$this->expectException(NotFoundException::class);
		$actual = $this->assignmentService->unassignUser(123, 'user');
	}
}
