<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck;

/**
 * User facing exception that can be thrown with an error being reported to the frontend
 * or consumers of the API
 *
 * This exception is catched in the ExceptionMiddleware
 */
class StatusException extends \Exception {
	public function __construct($message) {
		parent::__construct($message ?? '');
	}

	public function getStatus() {
		return 500;
	}
}
