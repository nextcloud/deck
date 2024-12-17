<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Event;

use OCA\Deck\Db\Acl;
use OCP\EventDispatcher\Event;

abstract class AAclEvent extends Event {
	private $acl;

	public function __construct(Acl $acl) {
		parent::__construct();

		$this->acl = $acl;
	}

	public function getAcl(): Acl {
		return $this->acl;
	}

	public function getBoardId(): int {
		return $this->acl->getBoardId();
	}
}
