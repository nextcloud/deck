<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Exceptions;

use OCA\Deck\BadRequestException;

class FederationDisabledException extends BadRequestException {
	public function __construct() {
		parent::__construct("Federation is disabled");
	}
}
