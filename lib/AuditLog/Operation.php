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

	// Stacks, cards and labels
	case StackDeleted = 'deck.stack.deleted';
	case StackRestored = 'deck.stack.restored';
	case CardCreated = 'deck.card.created';
	case CardDeleted = 'deck.card.deleted';
	case CardRestored = 'deck.card.restored';
	case CardMoved = 'deck.card.moved';
	case CardArchived = 'deck.card.archived';
	case CardUnarchived = 'deck.card.unarchived';
	case CardAssignmentCreated = 'deck.card_assignment.created';
	case CardAssignmentDeleted = 'deck.card_assignment.deleted';
	case LabelDeleted = 'deck.label.deleted';

	// Attachments
	case AttachmentCreated = 'deck.attachment.created';
	case AttachmentDeleted = 'deck.attachment.deleted';
	case AttachmentRestored = 'deck.attachment.restored';

	// Comments
	case CommentCreated = 'deck.comment.created';
	case CommentUpdated = 'deck.comment.updated';
	case CommentDeleted = 'deck.comment.deleted';

	// Administration
	case ConfigGroupLimitUpdated = 'deck.config.group_limit_updated';
	case ConfigFederationUpdated = 'deck.config.federation_updated';

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
			self::StackDeleted => 'A stack was moved to the trash with all its cards',
			self::StackRestored => 'A stack was restored from the trash',
			self::CardCreated => 'A card was created',
			self::CardDeleted => 'A card was moved to the trash',
			self::CardRestored => 'A card was restored from the trash',
			self::CardMoved => 'A card was moved to another board',
			self::CardArchived => 'A card was archived',
			self::CardUnarchived => 'A card was unarchived',
			self::CardAssignmentCreated => 'A user, group or team was assigned to a card',
			self::CardAssignmentDeleted => 'A user, group or team was unassigned from a card',
			self::LabelDeleted => 'A label was deleted and removed from all cards of its board',
			self::AttachmentCreated => 'A file stored by Deck was attached to a card',
			self::AttachmentDeleted => 'A file stored by Deck was deleted from a card',
			self::AttachmentRestored => 'A deleted file stored by Deck was restored on a card',
			self::CommentCreated => 'A comment was added to a card',
			self::CommentUpdated => 'A comment on a card was edited by its author',
			self::CommentDeleted => 'A comment was deleted from a card by its author',
			self::ConfigGroupLimitUpdated => 'The groups allowed to use Deck were changed',
			self::ConfigFederationUpdated => 'Sharing boards with other servers was turned on or off',
		};
	}
}
