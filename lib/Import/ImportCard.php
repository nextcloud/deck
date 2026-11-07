<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * One card to import
 *
 * @since 2.0.0
 */
final class ImportCard {
	/** A new card; a reference Deck holds with other content is a conflict (`exists`) */
	public const MODE_CREATE = 'create';
	/** A new version of a card the importer wrote */
	public const MODE_NEW_VERSION = 'new_version';

	/**
	 * @param string $ref unique on the board, at most 255 bytes
	 * @param string $stackRef a stack of the board (IBoardImporter::ensureBoard)
	 * @param string $title 1 to 255 characters
	 * @param string $description Markdown; checklists are task lists ("- [ ] item")
	 * @param string $createdBy the user who created the card in the source; '' for the board owner
	 * @param list<string> $assignees user IDs
	 * @param list<string> $labels label refs of the board
	 * @param list<int> $files IDs of existing files, attached as Deck file attachments
	 * @param string $sha256 hex SHA-256 of the card's canonical form (CardCanonicalForm) as the importing app computed it
	 * @param self::MODE_* $mode
	 */
	public function __construct(
		public readonly string $ref,
		public readonly string $stackRef,
		public readonly string $title,
		public readonly string $description,
		public readonly int $order,
		public readonly ?\DateTimeImmutable $start,
		public readonly ?\DateTimeImmutable $due,
		public readonly ?\DateTimeImmutable $done,
		public readonly \DateTimeImmutable $created,
		public readonly string $createdBy,
		public readonly array $assignees,
		public readonly array $labels,
		public readonly array $files,
		public readonly string $sha256,
		public readonly string $mode = self::MODE_CREATE,
	) {
	}
}
