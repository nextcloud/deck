<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Event;

use OCA\Deck\Db\Stack;

class StackUpdatedEvent extends AStackEvent {
	private ?Stack $stackBefore;

	public function __construct(Stack $stack, ?Stack $before = null) {
		parent::__construct($stack);

		$this->stackBefore = $before;
	}

	public function getBefore(): ?Stack {
		return $this->stackBefore;
	}
}
