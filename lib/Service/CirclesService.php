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
use OCP\App\IAppManager;
use OCP\Server;
use Throwable;

/**
 * Wrapper around circles app API since it is not in a public namespace so we need to make sure that
 * having the app disabled is properly handled
 */
class CirclesService {
	private bool $circlesEnabled;

	private $userCircleCache = [];

	public function __construct(IAppManager $appManager) {
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
			$circlesManager = Server::get(CirclesManager::class);
			$circlesManager->startSuperSession();
			return $circlesManager->getCircle($circleId);
		} catch (Throwable $e) {
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
			$circlesManager = Server::get(CirclesManager::class);
			$federatedUser = $circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$circlesManager->startSession($federatedUser);
			$circle = $circlesManager->getCircle($circleId);
			$member = $circle->getInitiator();
			$isUserInCircle = $member !== null && $member->getLevel() >= Member::LEVEL_MEMBER;

			if (!isset($this->userCircleCache[$circleId])) {
				$this->userCircleCache[$circleId] = [];
			}
			$this->userCircleCache[$circleId][$userId] = $isUserInCircle;

			return $isUserInCircle;
		} catch (Throwable $e) {
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

		try {
			$circlesManager = Server::get(CirclesManager::class);
			$federatedUser = $circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$circlesManager->startSession($federatedUser);
			$probe = new CircleProbe();
			$probe->mustBeMember();
			return array_map(function (Circle $circle) {
				return $circle->getSingleId();
			}, $circlesManager->getCircles($probe));
		} catch (Throwable $e) {
		}
		return [];
	}
}
