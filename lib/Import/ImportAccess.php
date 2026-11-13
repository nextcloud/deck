<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * One share of a board the importer manages
 *
 * @since 2.0.0
 */
final class ImportAccess {
	public const TYPE_USER = 'user';
	public const TYPE_GROUP = 'group';

	/**
	 * @param string $type self::TYPE_USER or self::TYPE_GROUP
	 * @param string $id the user or group ID
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $id,
		public readonly bool $edit,
		public readonly bool $share,
		public readonly bool $manage,
	) {
	}

	/**
	 * The reference the importer keeps for this share
	 */
	public function ref(): string {
		return $this->type . ':' . $this->id;
	}
}
