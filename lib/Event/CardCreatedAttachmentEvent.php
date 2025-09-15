<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Event;

use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\Card;

class CardCreatedAttachmentEvent extends ACardEvent {
	private Attachment $attachment;

	public function __construct(Card $card, Attachment $attachment)
	{
		parent::__construct($card);

		$this->attachment = $attachment;
	}

	public function getAttachment(): Attachment
	{
		return $this->attachment;
	}
}
