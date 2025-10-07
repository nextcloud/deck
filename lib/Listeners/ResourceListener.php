<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Listeners;

use OCA\Deck\Collaboration\Resources\ResourceProvider;
use OCA\Deck\Collaboration\Resources\ResourceProviderCard;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\ResourceException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<Event|AclDeletedEvent|AclCreatedEvent> */
class ResourceListener implements IEventListener {

	public function __construct(
		private readonly IManager $resourceManager,
		private readonly ResourceProviderCard $resourceProviderCard,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AclDeletedEvent && !$event instanceof AclCreatedEvent) {
			return;
		}

		$boardId = $event->getAcl()->getBoardId();

		$this->resourceManager->invalidateAccessCacheForProvider($this->resourceProviderCard);

		try {
			$resource = $this->resourceManager->getResourceForUser(ResourceProvider::RESOURCE_TYPE, (string)$boardId, null);
			$this->resourceManager->invalidateAccessCacheForResource($resource);
		} catch (ResourceException $e) {
			// If there is no resource we don't need to invalidate anything, but this should not happen anyway
		}
	}
}
