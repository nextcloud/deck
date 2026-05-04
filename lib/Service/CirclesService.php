<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Model\Probes\DataProbe;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Wrapper around circles app API since it is not in a public namespace so we need to make sure that
 * having the app disabled is properly handled
 */
class CirclesService {
	private bool $circlesEnabled;

	private $userCircleCache = [];
	/** @var array<string, string[]> */
	private array $userCirclesCache = [];

	public function __construct(IAppManager $appManager, private ?LoggerInterface $logger = null) {
		$this->circlesEnabled = $appManager->isEnabledForUser('circles');
	}

	public function isCirclesEnabled(): bool {
		return $this->circlesEnabled;
	}

	public function getCircle(string $circleId): ?Circle {
		if (!$this->circlesEnabled) {
			return null;
		}

		try {
			// Enforce current user condition since we always want the full list of members
			$circlesManager = $this->getCirclesManager();
			$circlesManager->startSuperSession();
			try {
				$dataProbe = new DataProbe();
				$dataProbe->add(DataProbe::OWNER);
				return $circlesManager->probeCircle($circleId, null, $dataProbe);
			} finally {
				$this->stopSessionSafely($circlesManager, 'getCircle');
			}
		} catch (Throwable $e) {
			$this->logger?->debug('CirclesService::getCircle failed', [
				'circleId' => $circleId,
				'exception' => $e,
			]);
		}
		return null;
	}

	public function isUserInCircle(string $circleId, string $userId): bool {
		if (!$this->circlesEnabled) {
			return false;
		}

		if (isset($this->userCircleCache[$circleId][$userId])) {
			return $this->userCircleCache[$circleId][$userId];
		}

		try {
			$circlesManager = $this->getCirclesManager();
			$federatedUser = $circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$circlesManager->startSession($federatedUser);
			try {
				$dataProbe = new DataProbe();
				$dataProbe->add(DataProbe::INITIATOR);
				$circle = $circlesManager->probeCircle($circleId, null, $dataProbe);
				$member = $circle->getInitiator();
				$isUserInCircle = $member->getLevel() >= Member::LEVEL_MEMBER;
			} finally {
				$this->stopSessionSafely($circlesManager, 'isUserInCircle');
			}

			if (!isset($this->userCircleCache[$circleId])) {
				$this->userCircleCache[$circleId] = [];
			}
			$this->userCircleCache[$circleId][$userId] = $isUserInCircle;

			return $isUserInCircle;
		} catch (Throwable $e) {
			$this->logger?->debug('CirclesService::isUserInCircle failed', [
				'circleId' => $circleId,
				'userId' => $userId,
				'exception' => $e,
			]);
		}
		return false;
	}

	/**
	 * @param string $userId
	 * @return string[] circle single ids
	 */
	public function getUserCircles(string $userId): array {
		if (!$this->circlesEnabled) {
			return [];
		}

		if (isset($this->userCirclesCache[$userId])) {
			return $this->userCirclesCache[$userId];
		}

		try {
			$circlesManager = $this->getCirclesManager();
			$federatedUser = $circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$circlesManager->startSession($federatedUser);
			try {
				$probe = new CircleProbe();
				$probe->mustBeMember();
				$circles = array_map(function (Circle $circle) {
					return $circle->getSingleId();
				}, $circlesManager->probeCircles($probe));
			} finally {
				$this->stopSessionSafely($circlesManager, 'getUserCircles');
			}
			$this->userCirclesCache[$userId] = $circles;
			return $circles;
		} catch (Throwable $e) {
			$this->logger?->debug('CirclesService::getUserCircles failed', [
				'userId' => $userId,
				'exception' => $e,
			]);
		}
		return [];
	}

	public function clearUserCircleCache(?string $circleId = null, ?string $userId = null): void {
		if ($circleId === null && $userId === null) {
			$this->userCircleCache = [];
			return;
		}

		if ($circleId !== null && $userId === null) {
			unset($this->userCircleCache[$circleId]);
			return;
		}

		if ($circleId !== null && $userId !== null) {
			unset($this->userCircleCache[$circleId][$userId]);
			if (empty($this->userCircleCache[$circleId])) {
				unset($this->userCircleCache[$circleId]);
			}
			return;
		}

		foreach ($this->userCircleCache as $cachedCircleId => $users) {
			unset($users[$userId]);
			if (empty($users)) {
				unset($this->userCircleCache[$cachedCircleId]);
				continue;
			}
			$this->userCircleCache[$cachedCircleId] = $users;
		}
	}

	public function clearUserCirclesCache(?string $userId = null): void {
		if ($userId === null) {
			$this->userCirclesCache = [];
			return;
		}

		unset($this->userCirclesCache[$userId]);
	}

	public function clearAllCaches(): void {
		$this->clearUserCircleCache();
		$this->clearUserCirclesCache();
	}

	protected function getCirclesManager(): CirclesManager {
		return Server::get(CirclesManager::class);
	}

	private function stopSessionSafely(CirclesManager $circlesManager, string $method): void {
		if (!method_exists($circlesManager, 'stopSession')) {
			return;
		}

		try {
			$circlesManager->stopSession();
		} catch (Throwable $e) {
			$this->logger?->debug('CirclesService::' . $method . ' stopSession failed', [
				'exception' => $e,
			]);
		}
	}
}
