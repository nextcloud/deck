<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer;

/**
 * Selects which parts of an import file are actually applied.
 *
 * Everything is imported by default; the flags let a user restore only a
 * subset, for example to build a board template from an existing export by
 * importing the lists and labels without any cards.
 */
class ImportOptions {
	public function __construct(
		public readonly bool $importCards = true,
		public readonly bool $importArchivedCards = true,
		public readonly bool $importDoneState = true,
		public readonly bool $importDueDates = true,
		public readonly bool $importLabels = true,
		public readonly bool $importAssignments = true,
		public readonly bool $importComments = true,
		public readonly bool $importAttachments = true,
		public readonly bool $importSharing = true,
	) {
	}

	/**
	 * Build options from untyped request input. Missing keys keep the default
	 * of importing everything.
	 *
	 * @param array<string, mixed> $values
	 */
	public static function fromArray(array $values): self {
		$flag = static function (string $key) use ($values): bool {
			if (!array_key_exists($key, $values)) {
				return true;
			}
			return filter_var($values[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
		};

		return new self(
			importCards: $flag('importCards'),
			importArchivedCards: $flag('importArchivedCards'),
			importDoneState: $flag('importDoneState'),
			importDueDates: $flag('importDueDates'),
			importLabels: $flag('importLabels'),
			importAssignments: $flag('importAssignments'),
			importComments: $flag('importComments'),
			importAttachments: $flag('importAttachments'),
			importSharing: $flag('importSharing'),
		);
	}
}
