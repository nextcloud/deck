<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Service;

/**
 * Interface to implement in case attachments are handled by a different backend than
 * then oc_deck_attachments table, e.g. for file sharing. When this interface is used
 * for implementing an attachment handler no backlink will be stored in the deck attachments
 * table and it is up to the implementation to track attachment to card relation.
 */
interface ICustomAttachmentService {
	public function listAttachments(int $cardId): array;

	public function getAttachmentCount(int $cardId): int;
}
