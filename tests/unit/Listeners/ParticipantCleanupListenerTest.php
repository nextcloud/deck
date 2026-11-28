<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Listeners;

if (!class_exists(\OCA\Circles\Model\Circle::class)) {
	require_once __DIR__ . '/../stubs/CirclesCircle.php';
}
if (!class_exists(\OCA\Circles\Model\Member::class)) {
	require_once __DIR__ . '/../stubs/CirclesMember.php';
}
if (!class_exists(\OCA\Circles\Events\CircleDestroyedEvent::class)) {
	require_once __DIR__ . '/../stubs/CirclesCircleDestroyedEvent.php';
}
if (!class_exists(\OCA\Circles\Events\RemovingCircleMemberEvent::class)) {
	require_once __DIR__ . '/../stubs/CirclesRemovingCircleMemberEvent.php';
}

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CirclesService;
use OCA\Deck\Service\TeamBoardService;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IGroup;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParticipantCleanupListenerTest extends TestCase {
	private BoardService&MockObject $boardService;
	private BoardMapper&MockObject $boardMapper;
	private TeamBoardService&MockObject $teamBoardService;
	private ParticipantCleanupListener $listener;

	public function setUp(): void {
		parent::setUp();
		$aclMapper = $this->createMock(AclMapper::class);
		$aclMapper->method('findByParticipant')->willReturn([]);
		$assignmentMapper = $this->createMock(AssignmentMapper::class);
		$assignmentMapper->method('findByParticipant')->willReturn([]);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->boardMapper->method('findAllByOwner')->willReturn([]);
		$this->teamBoardService = $this->createMock(TeamBoardService::class);
		$this->boardService = $this->createMock(BoardService::class);

		$this->listener = new ParticipantCleanupListener(
			$aclMapper,
			$assignmentMapper,
			$this->boardMapper,
			$this->createMock(CirclesService::class),
			$this->teamBoardService,
			$this->boardService,
		);
	}

	public function testDeletedUserIsRemovedFromSwimlaneOrders(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('ann');
		$event = $this->createMock(UserDeletedEvent::class);
		$event->method('getUser')->willReturn($user);

		$this->boardService->expects($this->once())
			->method('removeSwimlaneUserKey')
			->with(Acl::PERMISSION_TYPE_USER, 'ann');

		$this->listener->handle($event);
	}

	public function testDeletedGroupIsRemovedFromSwimlaneOrders(): void {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('sales');
		$event = $this->createMock(GroupDeletedEvent::class);
		$event->method('getGroup')->willReturn($group);

		$this->boardService->expects($this->once())
			->method('removeSwimlaneUserKey')
			->with(Acl::PERMISSION_TYPE_GROUP, 'sales');

		$this->listener->handle($event);
	}

	public function testDestroyedTeamIsRemovedFromSwimlaneOrdersBySingleId(): void {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn('single123');
		$event = $this->createMock(CircleDestroyedEvent::class);
		$event->method('getCircle')->willReturn($circle);

		$this->boardService->expects($this->once())
			->method('removeSwimlaneUserKey')
			->with(Acl::PERMISSION_TYPE_CIRCLE, 'single123');

		$this->listener->handle($event);
	}

	public function testMemberLeavingATeamKeepsSwimlaneOrders(): void {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn('single123');
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn(Member::TYPE_USER);
		$member->method('getUserId')->willReturn('ann');
		$event = $this->createMock(RemovingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($circle);
		$event->method('getMember')->willReturn($member);

		// The team still exists, so its lane stays. The member's own lane is
		// not affected either, the user is not deleted.
		$this->teamBoardService->expects($this->once())
			->method('handleMemberLeftTeam')
			->with('single123', 'ann');
		$this->boardService->expects($this->never())->method('removeSwimlaneUserKey');

		$this->listener->handle($event);
	}
}
