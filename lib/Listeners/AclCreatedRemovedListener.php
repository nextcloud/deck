<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Listeners;

use OCA\Circles\Model\Member;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\Service\CirclesService;
use OCA\Files_Sharing\Event\UserShareAccessUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IShare;

/** @template-implements IEventListener<Event|AclDeletedEvent|AclCreatedEvent> */
class AclCreatedRemovedListener implements IEventListener {
	public function __construct(
		private CirclesService $circlesService,
		private IGroupManager $groupManager,
		private IEventDispatcher $eventDispatcher,
		private IUserManager $userManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AclDeletedEvent && !$event instanceof AclCreatedEvent) {
			return;
		}

		if (!class_exists(UserShareAccessUpdatedEvent::class)) {
			return;
		}

		$acl = $event->getAcl();
		switch ($acl->getType()) {
			case IShare::TYPE_GROUP:
				$group = $this->groupManager->get($acl->getParticipant());
				foreach ($group->getUsers() as $user) {
					$this->eventDispatcher->dispatchTyped(new UserShareAccessUpdatedEvent($user));
				}
				break;
			case IShare::TYPE_CIRCLE:
				if (!$this->circlesService->isCirclesEnabled()) {
					return;
				}
				$circle = $this->circlesService->getCircle($acl->getParticipant());
				$members = array_filter($circle->getInheritedMembers(), static function (Member $member) {
					return $member->getUserType() === Member::TYPE_USER;
				});
				foreach ($members as $member) {
					$user = $this->userManager->get($member->getUserId());
					$this->eventDispatcher->dispatchTyped(new UserShareAccessUpdatedEvent($user));
				}
				break;
			default:
				$user = $this->userManager->get($acl->getParticipant());

				// for federated participants userManager might return null
				// disabling this for now as attachments for federated shares are not supported yet
				// @TODO: add event dispatching for federated shares
				if (is_null($user)) {
					break;
				}
				$this->eventDispatcher->dispatchTyped(new UserShareAccessUpdatedEvent($user));
				break;
		}
	}
}
