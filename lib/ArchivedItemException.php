<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck;

class ArchivedItemException extends \Exception {
	/**
	 * Constructor
	 * @param string $msg the error message
	 */
	public function __construct($msg = 'Operation not allowed. Item is archived.') {
		parent::__construct($msg);
	}
}
