<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Listeners;

use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\AAclEvent;
use OCA\Deck\Event\ACardEvent;
use OCA\Deck\Event\BoardUpdatedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Event\SessionClosedEvent;
use OCA\Deck\Event\SessionCreatedEvent;
use OCA\Deck\NotifyPushEvents;
use OCA\Deck\Service\SessionService;
use OCA\NotifyPush\Queue\IQueue;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event|SessionCreatedEvent|SessionClosedEvent|AAclEvent|ACardEvent|CardUpdatedEvent|BoardUpdatedEvent> */
class LiveUpdateListener implements IEventListener {
	private LoggerInterface $logger;
	private SessionService $sessionService;
	private IRequest $request;
	private StackMapper $stackMapper;
	private $queue;

	public function __construct(
		ContainerInterface $container,
		IRequest $request,
		LoggerInterface $logger,
		SessionService $sessionService,
		StackMapper $stackMapper,
	) {
		try {
			$this->queue = $container->get(IQueue::class);
		} catch (\Exception $e) {
			// most likely notify_push is not installed.
			return;
		}
		$this->logger = $logger;
		$this->sessionService = $sessionService;
		$this->request = $request;
		$this->stackMapper = $stackMapper;
	}

	public function handle(Event $event): void {
		if (!$this->queue) {
			// notify_push is not active
			return;
		}

		try {
			// the web frontend is adding the Session-ID as a header
			// TODO: verify the token! this currently allows to spoof a token from someone
			// else, preventing this person from getting updates
			$causingSessionToken = $this->request->getHeader('x-nc-deck-session');
			if (
				$event instanceof SessionCreatedEvent ||
				$event instanceof SessionClosedEvent ||
				$event instanceof BoardUpdatedEvent ||
				$event instanceof AAclEvent
			) {
				$this->sessionService->notifyAllSessions($this->queue, $event->getBoardId(), NotifyPushEvents::DeckBoardUpdate, [
					'id' => $event->getBoardId()
				], $causingSessionToken);
			} elseif ($event instanceof ACardEvent) {
				$boardId = $this->stackMapper->findBoardId($event->getCard()->getStackId());
				$this->sessionService->notifyAllSessions($this->queue, $boardId, NotifyPushEvents::DeckCardUpdate, [
					'boardId' => $boardId,
					'cardId' => $event->getCard()->getId()
				], $causingSessionToken);

				// if card got moved to a diferent board, we should notify
				// also sessions active on the previous board
				if ($event instanceof CardUpdatedEvent && $event->getCardBefore()) {
					$previousBoardId = $this->stackMapper->findBoardId($event->getCardBefore()->getStackId());
					if ($boardId !== $previousBoardId) {
						$this->sessionService->notifyAllSessions($this->queue, $previousBoardId, NotifyPushEvents::DeckCardUpdate, [
							'boardId' => $boardId,
							'cardId' => $event->getCard()->getId()
						], $causingSessionToken);
					}
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Error when handling live update event', ['exception' => $e]);
		}
	}
}
