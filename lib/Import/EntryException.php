<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * Ends the write of one entry, rolling back its transaction, with the
 * result the entry answers
 *
 * @internal
 */
class EntryException extends \Exception {
	public function __construct(
		public readonly ImportResult $result,
	) {
		parent::__construct((string)$result->errorMessage);
	}

	public static function error(string $code, string $message): self {
		return new self(ImportResult::error($code, $message));
	}

	public static function conflict(string $code, string $message, ?int $cardId = null, ?string $sha256 = null): self {
		return new self(ImportResult::conflict($code, $message, $cardId, $sha256));
	}
}
