<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * Imports boards from another system and keeps them in step with it
 *
 * Unlike `occ deck:import`, which creates a new board on every run, an
 * importing app addresses everything it writes by its own references, so
 * every call can be repeated and later calls carry the source's changes.
 * Deck keeps the references in `deck_import_refs`, per importing app.
 *
 * Imports are quiet: no activity, notification, mention, mail or push
 * reaches anybody and no Deck event is dispatched, so a migration of
 * thousands of cards doesn't flood the people it moves. Full-text search,
 * collaboration resources, share access and the admin audit log are kept
 * up to date.
 *
 * Nothing the importer wrote is overwritten after somebody changed it in
 * Deck: such a card answers `changed` and keeps the user's version.
 *
 * The API has no user context and checks no Deck permissions: the caller
 * authorizes the importing app. It only changes boards the same app
 * created and throws an ImportException for any other board.
 *
 * @since 2.0.0
 */
interface IBoardImporter {
	/**
	 * Creates or updates by $ref: title, color, labels and stacks (by their refs).
	 *
	 * A board, label or stack somebody changed in Deck since the last import
	 * keeps its Deck version; its reference is listed in `changed`. Labels
	 * and stacks missing from the lists stay on the board. The answer maps
	 * the refs of all given labels and stacks to their IDs.
	 *
	 * @param string $app the importing app, [a-z0-9_]{1,32}
	 * @param string $ref the app's reference of the board, at most 255 bytes
	 * @param string $ownerUid the owner of a new board; an existing board keeps its owner
	 * @param string $color rrggbb
	 * @param \DateTimeImmutable $created the source's creation time (Deck boards don't store one)
	 * @param list<ImportLabel> $labels
	 * @param list<ImportStack> $stacks
	 * @return array{status: string, board_id: int, created: bool, labels: array<string, int>, stacks: array<string, int>, changed: list<string>, error?: array{code: string, message: string}}
	 */
	public function ensureBoard(string $app, string $ref, string $ownerUid, string $title, string $color,
		\DateTimeImmutable $created, array $labels, array $stacks): array;

	/**
	 * Writes cards with their original creation, start, due and done times,
	 * assignments, labels and file attachments.
	 *
	 * @param list<ImportCard> $cards
	 * @return list<ImportResult> one per card, in the same order
	 * @throws ImportException when the board is missing or another app created it
	 */
	public function importCards(string $app, int $boardId, array $cards): array;

	/**
	 * What Deck holds for each reference, with the SHA-256 of the canonical
	 * form (CardCanonicalForm) when $hash is set.
	 *
	 * @param list<string> $refs
	 * @return list<ImportCardStat> one per reference, in the same order
	 * @throws ImportException
	 */
	public function statCards(string $app, int $boardId, array $refs, bool $hash): array;

	/**
	 * Pages through the cards the app wrote to a board and didn't remove.
	 *
	 * @param string $cursor '' for the first page, then the previous `next_cursor`
	 * @param int $limit 1 to 1000
	 * @return array{entries: list<string>, next_cursor: string} the card refs; `next_cursor` is '' on the last page
	 * @throws ImportException
	 */
	public function listCards(string $app, int $boardId, string $cursor, int $limit): array;

	/**
	 * Soft-deletes cards the importer wrote (Deck's deleted cards).
	 *
	 * @param list<string> $refs
	 * @return list<ImportResult> one per reference, in the same order
	 * @throws ImportException
	 */
	public function removeCards(string $app, int $boardId, array $refs): array;

	/**
	 * The complete list of shares the importer manages; no notification.
	 *
	 * Shares the importer made that are missing from $acl are removed;
	 * shares others made stay as they are.
	 *
	 * @param list<ImportAccess> $acl
	 * @return list<ImportResult> one per share, in the same order
	 * @throws ImportException
	 */
	public function setAccess(string $app, int $boardId, array $acl): array;
}
