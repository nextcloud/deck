<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Event;

use OCA\Deck\Db\Assignment;
use OCP\EventDispatcher\Event;

class AAssignmentEvent extends Event
{
	private Assignment $assignment;

	public function __construct(Assignment $assignment) {
		parent::__construct();

		$this->assignment = $assignment;
	}

	public function getAssignement(): Assignment
	{
		return$this->assignment;
	}
}
