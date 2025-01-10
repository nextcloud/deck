<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck;

class NoPermissionException extends StatusException {
	public function __construct($message, $controller = null, $method = null) {
		parent::__construct($message);
		if ($controller && $method) {
			$this->message = get_class($controller) . '#' . $method . ': ' . $message;
		}
	}

	public function getStatus() {
		return 403;
	}
}
