<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\Deck\Listeners;

use OCA\Deck\NotifyPushEvents;
use OCA\Deck\Event\SessionClosedEvent;
use OCA\Deck\Event\SessionCreatedEvent;
use OCA\Deck\Service\SessionService;
use OCA\NotifyPush\Queue\IQueue;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event|SessionCreatedEvent|SessionClosedEvent> */
class LiveUpdateListener implements IEventListener {
	private LoggerInterface $logger;
	private SessionService $sessionService;
	private IRequest $request;
	private $queue;

	public function __construct(
		ContainerInterface $container,
		IRequest $request,
		LoggerInterface $logger,
		SessionService $sessionService
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
	}

	public function handle(Event $event): void {
		if (!$this->queue) {
			// notify_push is not active
			return;
		}

		try {
			// the web frontend is adding the Session-ID as a header on every request
			// TODO: verify the token! this currently allows to spoof a token from someone
			// else, preventing this person from getting any live updates
			$causingSessionToken = $this->request->getHeader('x-nc-deck-session');
			if (
				$event instanceof SessionCreatedEvent ||
				$event instanceof SessionClosedEvent
			) {
				$this->sessionService->notifyAllSessions($this->queue, $event->getBoardId(), NotifyPushEvents::DeckBoardUpdate, [
					'id' => $event->getBoardId()
				], $causingSessionToken);
			}
		} catch (\Exception $e) {
			$this->logger->error('Error when handling live update event', ['exception' => $e]);
		}
	}
}
