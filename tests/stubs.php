<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Stub class definitions for optional app dependencies.
 * These are only defined when the real classes are not available (e.g. in CI
 * environments where the circles app is not installed), allowing PHPUnit to
 * create mocks of them in unit tests.
 */

namespace OCA\Circles\Model;

if (!class_exists(\OCA\Circles\Model\Member::class)) {
	class Member {
		public const LEVEL_NONE = 0;
		public const LEVEL_MEMBER = 1;
		public const LEVEL_MODERATOR = 4;
		public const LEVEL_ADMIN = 8;
		public const LEVEL_OWNER = 9;

		public const TYPE_SINGLE = 0;
		public const TYPE_USER = 1;
		public const TYPE_GROUP = 2;
		public const TYPE_MAIL = 4;
		public const TYPE_CONTACT = 8;
		public const TYPE_CIRCLE = 16;
		public const TYPE_APP = 10000;

		public function getLevel(): int {
			return 0;
		}
		public function getUserType(): int {
			return 0;
		}
		public function getUserId(): string {
			return '';
		}
	}
}

if (!class_exists(\OCA\Circles\Model\Circle::class)) {
	class Circle {
		public function getUniqueId(): string {
			return '';
		}
		public function getDisplayName(): string {
			return '';
		}
		public function getSingleId(): string {
			return '';
		}
		public function getInheritedMembers(): array {
			return [];
		}
		public function getInitiator(): Member {
			return new Member();
		}
	}
}

if (!class_exists(\OCA\Circles\Model\FederatedUser::class)) {
	class FederatedUser {
	}
}

namespace OCA\Circles\Model\Probes;

if (!class_exists(\OCA\Circles\Model\Probes\CircleProbe::class)) {
	class CircleProbe {
		public function mustBeMember(bool $must = true): self {
			return $this;
		}
	}
}

if (!class_exists(\OCA\Circles\Model\Probes\DataProbe::class)) {
	class DataProbe {
		public const OWNER = 'd';
		public const INITIATOR = 'h';

		public function add(string $key, array $path = []): self {
			return $this;
		}
	}
}

namespace OCA\Circles;

use OCA\Circles\Model\Circle;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Model\Probes\DataProbe;

if (!class_exists(\OCA\Circles\CirclesManager::class)) {
	class CirclesManager {
		public function startSuperSession(): void {
		}
		public function startSession(?FederatedUser $federatedUser = null): void {
		}
		public function stopSession(): void {
		}
		public function probeCircles(?CircleProbe $circleProbe = null, ?DataProbe $dataProbe = null): array {
			return [];
		}
		public function probeCircle(string $singleId, ?CircleProbe $probe = null, ?DataProbe $dataProbe = null): Circle {
			return new Circle();
		}
		public function getFederatedUser(string $federatedId, int $type = Member::TYPE_SINGLE): FederatedUser {
			return new FederatedUser();
		}
	}
}
