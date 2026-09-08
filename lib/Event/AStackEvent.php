<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Event;

use OCA\Deck\Db\Stack;
use OCP\EventDispatcher\Event;

abstract class AStackEvent extends Event {
	private Stack $stack;

	public function __construct(Stack $stack) {
		parent::__construct();

		$this->stack = $stack;
	}

	public function getStack(): Stack {
		return $this->stack;
	}
}
