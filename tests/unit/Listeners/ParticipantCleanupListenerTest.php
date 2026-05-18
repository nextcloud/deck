<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Model\Circle;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use PHPUnit\Framework\TestCase;

class ParticipantCleanupListenerTest extends TestCase {
	private AclMapper $aclMapper;
	private AssignmentMapper $assignmentMapper;
	private BoardMapper $boardMapper;
	private ParticipantCleanupListener $listener;

	protected function setUp(): void {
		parent::setUp();
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->listener = new ParticipantCleanupListener($this->aclMapper, $this->assignmentMapper, $this->boardMapper);
	}

	public function testCircleDestroyedDeletesCircleOwnedBoardsAndCleansParticipantData(): void {
		$circleId = 'circle-123';

		$circle = $this->createMock(Circle::class);
		$circle->expects($this->once())
			->method('getSingleId')
			->willReturn($circleId);

		$event = $this->createMock(CircleDestroyedEvent::class);
		$event->expects($this->once())
			->method('getCircle')
			->willReturn($circle);

		$boardOne = new Board();
		$boardTwo = new Board();
		$this->boardMapper->expects($this->once())
			->method('findAllByOwner')
			->with($circleId, Acl::PERMISSION_TYPE_CIRCLE)
			->willReturn([$boardOne, $boardTwo]);
		$this->boardMapper->expects($this->exactly(2))
			->method('delete');

		$acl = new Acl();
		$this->aclMapper->expects($this->once())
			->method('findByParticipant')
			->with(Acl::PERMISSION_TYPE_CIRCLE, $circleId)
			->willReturn([$acl]);
		$this->aclMapper->expects($this->once())
			->method('delete')
			->with($acl);

		$assignment = new Assignment();
		$this->assignmentMapper->expects($this->once())
			->method('findByParticipant')
			->with($circleId, Acl::PERMISSION_TYPE_CIRCLE)
			->willReturn([$assignment]);
		$this->assignmentMapper->expects($this->once())
			->method('delete')
			->with($assignment);

		$this->listener->handle($event);
	}
}
