<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022, chandi Langecker (git@chandi.it)
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Session;
use OCA\Deck\Db\SessionMapper;
use OCA\Deck\Event\SessionCreatedEvent;
use OCA\Deck\Event\SessionClosedEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCA\NotifyPush\Queue\IQueue;
use OCP\Security\ISecureRandom;

class SessionService {
	public const SESSION_VALID_TIME = 92;

	private SessionMapper $sessionMapper;
	private ITimeFactory $timeFactory;
	private $userId;
	private IEventDispatcher $eventDispatcher;
	private ISecureRandom $secureRandom;

	public function __construct(
		SessionMapper $sessionMapper,
		ISecureRandom $secureRandom,
		ITimeFactory $timeFactory,
		$userId,
		IEventDispatcher $eventDispatcher
	) {
		$this->sessionMapper = $sessionMapper;
		$this->secureRandom = $secureRandom;
		$this->timeFactory = $timeFactory;
		$this->userId = $userId;
		$this->eventDispatcher = $eventDispatcher;
	}

	public function initSession($boardId): Session {
		$session = new Session();
		$session->setBoardId($boardId);
		$session->setUserId($this->userId);
		$session->setToken($this->secureRandom->generate(64));
		$session->setLastContact($this->timeFactory->getTime());

		$session = $this->sessionMapper->insert($session);
		$this->eventDispatcher->dispatchTyped(new SessionCreatedEvent($boardId, $this->userId));
		return $session;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function syncSession(int $boardId, string $token) {
		$session = $this->sessionMapper->find($boardId, $this->userId, $token);
		$session->setLastContact($this->timeFactory->getTime());
		$this->sessionMapper->update($session);
	}

	public function closeSession(int $boardId, string $token): void {
		try {
			$session = $this->sessionMapper->find($boardId, $this->userId, $token);
			$this->sessionMapper->delete($session);
		} catch (DoesNotExistException $e) {
		}
		$this->eventDispatcher->dispatchTyped(new SessionClosedEvent($boardId, $this->userId));
	}
	
	public function removeInactiveSessions(): int {
		return $this->sessionMapper->deleteInactive();
	}

	public function notifyAllSessions(IQueue $queue, int $boardId, $event, $excludeUserId, $body) {
		$activeSessions = $this->sessionMapper->findAllActive($boardId);

		foreach ($activeSessions as $session) {
			$queue->push('notify_custom', [
				'user' => $session->getUserId(),
				'message' => $event,
				'body' => $body
			]);
		}
	}
}
