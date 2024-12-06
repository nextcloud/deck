<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Session;
use OCA\Deck\Db\SessionMapper;
use OCA\Deck\Event\SessionClosedEvent;
use OCA\Deck\Event\SessionCreatedEvent;
use OCA\NotifyPush\Queue\IQueue;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
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
		IEventDispatcher $eventDispatcher,
	) {
		$this->sessionMapper = $sessionMapper;
		$this->secureRandom = $secureRandom;
		$this->timeFactory = $timeFactory;
		$this->userId = $userId;
		$this->eventDispatcher = $eventDispatcher;
	}

	public function initSession(int $boardId): Session {
		$session = new Session();
		$session->setBoardId($boardId);
		$session->setUserId($this->userId);
		$session->setToken($this->secureRandom->generate(32));
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

	public function notifyAllSessions(IQueue $queue, int $boardId, $event, $body, $causingSessionToken = null) {
		$activeSessions = $this->sessionMapper->findAllActive($boardId);
		$userIds = [];
		foreach ($activeSessions as $session) {
			if ($causingSessionToken && $session->getToken() === $causingSessionToken) {
				// Limitation:
				// If the same user has a second session active, the session $causingSessionToken
				// still gets notified due to the current fact, that notify_push only supports
				// to specify users, not individual sessions
				// https://github.com/nextcloud/notify_push/issues/195
				continue;
			}

			// don't notify the same user multiple times
			if (!in_array($session->getUserId(), $userIds)) {
				$userIds[] = $session->getUserId();
			}
		}

		if ($causingSessionToken) {
			// we only send a slice of the session token to everyone
			// since knowledge of the full token allows everyone
			// to close the session maliciously
			$body['_causingSessionToken'] = substr($causingSessionToken, 0, 12);
		}
		foreach ($userIds as $userId) {
			$queue->push('notify_custom', [
				'user' => $userId,
				'message' => $event,
				'body' => $body
			]);
		}
	}
}
