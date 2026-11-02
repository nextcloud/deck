<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * Reads the boards a person owns or that are shared with them, for an
 * export of their data to another system
 *
 * A board is read whole: its labels, stacks and cards with their
 * assignees, labels, comments and attachments, as Deck shows them. Deleted
 * boards, stacks, cards and attachments are left out, archived ones are
 * read and marked. Boards of another server (federation) are not read:
 * their cards live there.
 *
 * Reading is quiet: it queries Deck's tables, the comments and the shares
 * directly, so no activity, notification, read mark or session changes,
 * no event is dispatched and nobody's file system is set up. Attachments
 * are described from the file cache and the shares; their bytes are read
 * through the files of their owner.
 *
 * The API has no user context and checks no Deck permissions beyond the
 * person having the board: the caller authorizes the read. Times are Unix
 * times.
 *
 * @since 2.0.0
 */
interface IBoardReader {
	public const ASSIGNEE_USER = 'user';
	public const ASSIGNEE_GROUP = 'group';
	public const ASSIGNEE_CIRCLE = 'circle';

	/** A file in Nextcloud shared to the card */
	public const ATTACHMENT_FILE = 'file';
	/** A file in Deck's own storage (older attachments) */
	public const ATTACHMENT_DECK_FILE = 'deck_file';

	/**
	 * The boards $userId owns or that are shared with them, directly, through
	 * a group or a team, by board ID
	 *
	 * @return list<array{id: int, title: string, owner: string, archived: bool, cards: int, last_modified: int}>
	 */
	public function boards(string $userId): array;

	/**
	 * One board of {@see self::boards()}, whole
	 *
	 * @return array{id: int, title: string, color: string, owner: string, archived: bool,
	 *     labels: list<array{id: int, title: string, color: string}>,
	 *     stacks: list<array{id: int, title: string, order: int, cards: list<array{
	 *         id: int, title: string, description: string, order: int, created: int, modified: int,
	 *         due: ?int, done: ?int, archived: bool, labels: list<int>,
	 *         assignees: list<array{type: string, id: string, display_name: string}>,
	 *         attachments: list<array{type: string, file_id: int, name: string, size: int}>,
	 *         comments: list<array{actor_type: string, actor_id: string, display_name: string, created: int, text: string}>,
	 *     }>}>}
	 * @throws ImportException NOT_FOUND when the person doesn't have the board
	 */
	public function board(string $userId, int $boardId): array;
}
