<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * A call the importer or the reader refuses as a whole
 *
 * @since 2.0.0
 */
class ImportException extends \Exception {
	/** The board doesn't exist, or the person doesn't have it ({@see IBoardReader}) */
	public const NOT_FOUND = 'not_found';
	/** The importing app didn't create the board */
	public const NOT_IMPORTER = 'not_importer';
	/** An argument of the call is invalid */
	public const INVALID = 'invalid';

	/**
	 * @param self::NOT_FOUND|self::NOT_IMPORTER|self::INVALID $reason
	 */
	public function __construct(
		private string $reason,
		string $message,
	) {
		parent::__construct($message);
	}

	/**
	 * @return self::NOT_FOUND|self::NOT_IMPORTER|self::INVALID
	 */
	public function getReason(): string {
		return $this->reason;
	}
}
