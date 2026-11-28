<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Circles\Events;

use OCA\Circles\Model\Circle;
use OCP\EventDispatcher\Event;

/**
 * Minimal stand-in for unit tests when the Circles app is not installed.
 */
class CircleDestroyedEvent extends Event {
	public function getCircle(): Circle {
		return new Circle();
	}
}
