<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Circles\Model;

/**
 * Minimal stand-in for unit tests when the Circles app is not installed.
 */
class Member {
	public const TYPE_USER = 1;

	public function getUserType(): int {
		return self::TYPE_USER;
	}

	public function getUserId(): string {
		return '';
	}
}
