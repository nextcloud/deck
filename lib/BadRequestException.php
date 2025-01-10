<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck;

use OCP\AppFramework\Http;

class BadRequestException extends StatusException {
	public function __construct($message) {
		parent::__construct($message);
	}

	public function getStatus() {
		return HTTP::STATUS_BAD_REQUEST;
	}
}
