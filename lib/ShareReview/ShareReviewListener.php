<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\ShareReview;

use OCA\ShareReview\Sources\SourceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<SourceEvent> */
class ShareReviewListener implements IEventListener {
	public function __construct() {
	}

	public function handle(Event $event): void {
		if (!$event instanceof SourceEvent) {
			return;
		}
		$event->registerSource(ShareReviewSource::class);
	}
}
