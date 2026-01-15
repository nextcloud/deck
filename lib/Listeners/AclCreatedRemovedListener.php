<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Listeners;

use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Files_Sharing\Event\UserShareAccessUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IShare;

class AclCreatedRemovedListener implements IEventListener {
	public function __construct(
		private IGroupManager $groupManager,
		private IEventDispatcher $eventDispatcher,
		private IUserManager $userManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AclDeletedEvent && !$event instanceof AclCreatedEvent) {
			return;
		}

		$acl = $event->getAcl();
		if ($acl->getType() === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($acl->getParticipant());
			foreach ($group->getUsers() as $user) {
				$this->eventDispatcher->dispatchTyped(new UserShareAccessUpdatedEvent($user));
			}
		} else {
			$user = $this->userManager->get($acl->getParticipant());
			$this->eventDispatcher->dispatchTyped(new UserShareAccessUpdatedEvent($user));
		}
	}
}
