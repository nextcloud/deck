<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\ShareReview;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Share\ShareReview\RegisterShareReviewSourceEvent;

/** @template-implements IEventListener<RegisterShareReviewSourceEvent> */
class ShareReviewListener implements IEventListener {
	public function __construct() {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RegisterShareReviewSourceEvent) {
			return;
		}
		$event->registerSource(ShareReviewSource::class);
	}
}
