<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Member;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\TeamBoardService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

/** @template-implements IEventListener<Event|UserDeletedEvent|GroupDeletedEvent|CircleDestroyedEvent|RemovingCircleMemberEvent> */
class ParticipantCleanupListener implements IEventListener {
	public function __construct(
		private AclMapper $aclMapper,
		private AssignmentMapper $assignmentMapper,
		private BoardMapper $boardMapper,
		private TeamBoardService $teamBoardService,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$userId = $event->getUser()->getUID();
			$transferredBoardIds = $this->teamBoardService->transferTeamBoardsFromDeletedUser($userId);

			$boards = $this->boardMapper->findAllByOwner($userId);
			foreach ($boards as $board) {
				if (in_array($board->getId(), $transferredBoardIds, true)) {
					continue;
				}
				$this->boardMapper->delete($board);
			}

			$this->cleanupByParticipant(Acl::PERMISSION_TYPE_USER, $userId);
		}

		if ($event instanceof GroupDeletedEvent) {
			$this->cleanupByParticipant(Acl::PERMISSION_TYPE_GROUP, $event->getGroup()->getGID());
		}

		if ($event instanceof RemovingCircleMemberEvent) {
			$member = $event->getMember();
			if ($member->getUserType() === Member::TYPE_USER) {
				$this->teamBoardService->handleMemberLeftTeam(
					$event->getCircle()->getSingleId(),
					$member->getUserId()
				);
			}
		}

		if ($event instanceof CircleDestroyedEvent) {
			$circleId = $event->getCircle()->getSingleId();
			$this->teamBoardService->deleteBoardsAttachedToTeam($circleId);
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
