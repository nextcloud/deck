<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Exceptions;

use OCA\Deck\StatusException;
use OCP\AppFramework\Http;

class PreconditionFailedException extends StatusException {
	public function getStatus() {
		return Http::STATUS_PRECONDITION_FAILED;
	}
}
