<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\AuditLog;

/**
 * Operations Deck writes to the admin audit log, in the form `app.entity.action`
 *
 * The values are matched by log consumers (filters, alert rules, SIEM imports),
 * so changing one is a breaking change for them. Case names may be renamed.
 */
enum Operation: string {
	// Boards
	case BoardCreated = 'deck.board.created';
	case BoardDeleted = 'deck.board.deleted';
	case BoardRestored = 'deck.board.restored';
	case BoardPurged = 'deck.board.purged';
	case BoardArchived = 'deck.board.archived';
	case BoardUnarchived = 'deck.board.unarchived';
	case BoardCloned = 'deck.board.cloned';
	case BoardImported = 'deck.board.imported';
	case BoardOwnershipTransferred = 'deck.board.ownership_transferred';

	// Sharing
	case BoardShareCreated = 'deck.board_share.created';
	case BoardShareUpdated = 'deck.board_share.updated';
	case BoardShareDeleted = 'deck.board_share.deleted';
	case BoardShareLeft = 'deck.board_share.left';
	case BoardShareReceived = 'deck.board_share.received';

	/**
	 * Short description of when the operation is logged, used for the documentation
	 */
	public function description(): string {
		return match ($this) {
			self::BoardCreated => 'A board was created',
			self::BoardDeleted => 'A board was moved to the trash',
			self::BoardRestored => 'A board was restored from the trash',
			self::BoardPurged => 'A board was permanently deleted, after the trash retention or together with its team',
			self::BoardArchived => 'A board was archived',
			self::BoardUnarchived => 'A board was unarchived',
			self::BoardCloned => 'A board was cloned into a new board',
			self::BoardImported => 'A board was imported from another system',
			self::BoardOwnershipTransferred => 'The ownership of a board was transferred to another user',
			self::BoardShareCreated => 'A board was shared with a user, group, team or remote user',
			self::BoardShareUpdated => 'The permissions of a board share were changed',
			self::BoardShareDeleted => 'A board share was removed',
			self::BoardShareLeft => 'A user left a board shared with them',
			self::BoardShareReceived => 'A board was shared with a local user from another server',
		};
	}
}
