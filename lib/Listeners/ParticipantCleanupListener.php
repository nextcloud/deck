<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

/** @template-implements IEventListener<Event|UserDeletedEvent|GroupDeletedEvent|CircleDestroyedEvent> */
class ParticipantCleanupListener implements IEventListener {
	private AclMapper $aclMapper;
	private AssignmentMapper $assignmentMapper;
	private BoardMapper $boardMapper;

	public function __construct(AclMapper $aclMapper, AssignmentMapper $assignmentMapper, BoardMapper $boardMapper) {
		$this->aclMapper = $aclMapper;
		$this->assignmentMapper = $assignmentMapper;
		$this->boardMapper = $boardMapper;
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$boards = $this->boardMapper->findAllByOwner($event->getUser()->getUID());
			foreach ($boards as $board) {
				$this->boardMapper->delete($board);
			}

			$this->cleanupByParticipant(Acl::PERMISSION_TYPE_USER, $event->getUser()->getUID());
		}

		if ($event instanceof GroupDeletedEvent) {
			$this->cleanupByParticipant(Acl::PERMISSION_TYPE_GROUP, $event->getGroup()->getGID());
		}

		if ($event instanceof CircleDestroyedEvent) {
			$circleId = $event->getCircle()->getSingleId();
			$this->cleanupByParticipant(Acl::PERMISSION_TYPE_CIRCLE, $circleId);
		}
	}

	private function cleanupByParticipant(int $type, string $participant): void {
		$acls = $this->aclMapper->findByParticipant($type, $participant);
		foreach ($acls as $acl) {
			$this->aclMapper->delete($acl);
		}

		$assignments = $this->assignmentMapper->findByParticipant($participant, $type);
		foreach ($assignments as $assignment) {
			$this->assignmentMapper->delete($assignment);
		}
	}
}
