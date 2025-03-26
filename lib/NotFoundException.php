<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck;

class NotFoundException extends StatusException {
	public function __construct($message = '') {
		parent::__construct($message);
	}

	public function getStatus() {
		return 404;
	}
}
