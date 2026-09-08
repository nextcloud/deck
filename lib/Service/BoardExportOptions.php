<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

/**
 * Controls how much of a board BoardExportService puts into the export.
 *
 * Everything is exported by default, the flags only exist so that callers can
 * leave out the parts that make an export large (attachment contents) or that
 * a specific consumer does not need.
 */
class BoardExportOptions {
	public function __construct(
		public readonly bool $includeArchivedCards = true,
		public readonly bool $includeComments = true,
		public readonly bool $includeAttachments = true,
	) {
	}
}
