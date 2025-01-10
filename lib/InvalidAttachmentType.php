<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck;

class InvalidAttachmentType extends \Exception {

	/**
	 * InvalidAttachmentType constructor.
	 */
	public function __construct($type) {
		parent::__construct('No matching IAttachmentService implementation found for type ' . $type);
	}
}
