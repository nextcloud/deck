<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * A board label to create or update by its reference
 *
 * @since 2.0.0
 */
final class ImportLabel {
	/**
	 * @param string $ref unique among the board's labels, at most 255 bytes
	 * @param string $title 1 to 100 characters, unique among the board's labels
	 * @param string $color rrggbb
	 */
	public function __construct(
		public readonly string $ref,
		public readonly string $title,
		public readonly string $color,
	) {
	}
}
