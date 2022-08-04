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
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class LiveUpdateListener implements IEventListener {
	private string $userId;
	private LoggerInterface $logger;
	private SessionService $sessionService;
	private $queue;
	
	public function __construct(ContainerInterface $container, SessionService $sessionService, $userId) {
		try {
			$this->queue = $container->get(IQueue::class);
		} catch (\Exception $e) {
			// most likely notify_push is not installed.
			return;
		}
		$this->userId = $userId;
		$this->logger = $container->get(LoggerInterface::class);
		$this->sessionService = $sessionService;
	}

	public function handle(Event $event): void {
		if (!$this->queue) {
			// notify_push is not active
			return;
		}

		try {
			if ($event instanceof SessionCreatedEvent || $event instanceof SessionClosedEvent) {
				$this->sessionService->notifyAllSessions($this->queue, $event->getBoardId(), NotifyPushEvents::DeckBoardUpdate, $event->getUserId(), [
					'id' => $event->getBoardId()
				]);
			}
		} catch (\Exception $e) {
			$this->logger->error('Error when handling live update event', ['exception' => $e]);
		}
	}
}
