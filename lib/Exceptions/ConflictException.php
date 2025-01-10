<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Exceptions;

use OCA\Deck\StatusException;

class ConflictException extends StatusException {
	private $data;

	public function __construct($message, $data = null) {
		parent::__construct($message);
		$this->data = $data;
	}

	public function getStatus() {
		return 409;
	}

	public function getData() {
		return $this->data;
	}
}
