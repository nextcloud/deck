<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * The importer's answer for one card or share
 *
 * @since 2.0.0
 */
final class ImportResult implements \JsonSerializable {
	public const STATUS_OK = 'ok';
	/** Deck holds something else for the reference and kept it */
	public const STATUS_CONFLICT = 'conflict';
	public const STATUS_ERROR = 'error';

	/** A field of the input is invalid; the message names it */
	public const ERROR_INVALID = 'invalid';
	/** MODE_CREATE for a reference Deck holds with other content */
	public const ERROR_EXISTS = 'exists';
	/** Somebody changed, archived or deleted the card or share in Deck since the import */
	public const ERROR_CHANGED = 'changed';
	/** The importer never wrote the reference */
	public const ERROR_NOT_FOUND = 'not_found';
	public const ERROR_UNKNOWN_USER = 'unknown_user';
	public const ERROR_UNKNOWN_GROUP = 'unknown_group';
	/** The stack reference wasn't ensured on the board */
	public const ERROR_UNKNOWN_STACK = 'unknown_stack';
	/** A label reference wasn't ensured on the board */
	public const ERROR_UNKNOWN_LABEL = 'unknown_label';
	/** A file doesn't exist or its owner may not share it */
	public const ERROR_FILE_UNAVAILABLE = 'file_unavailable';

	/**
	 * @param self::STATUS_* $status
	 * @param ?string $sha256 the SHA-256 of the canonical form of what Deck holds
	 */
	public function __construct(
		public readonly string $status,
		public readonly ?int $cardId = null,
		public readonly ?string $sha256 = null,
		public readonly ?string $errorCode = null,
		public readonly ?string $errorMessage = null,
	) {
	}

	public static function ok(?int $cardId = null, ?string $sha256 = null): self {
		return new self(self::STATUS_OK, $cardId, $sha256);
	}

	public static function conflict(string $code, string $message, ?int $cardId = null, ?string $sha256 = null): self {
		return new self(self::STATUS_CONFLICT, $cardId, $sha256, $code, $message);
	}

	public static function error(string $code, string $message): self {
		return new self(self::STATUS_ERROR, null, null, $code, $message);
	}

	/**
	 * @return array{status: string, card_id?: int, sha256?: string, error?: array{code: string, message: string}}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		$json = ['status' => $this->status];
		if ($this->cardId !== null) {
			$json['card_id'] = $this->cardId;
		}
		if ($this->sha256 !== null) {
			$json['sha256'] = $this->sha256;
		}
		if ($this->errorCode !== null) {
			$json['error'] = ['code' => $this->errorCode, 'message' => (string)$this->errorMessage];
		}
		return $json;
	}
}
