<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\ShareReview;

class ShareReviewShare {
	public function __construct(
		private readonly int $id,
		private readonly string $object,
		private readonly string $initiator,
		private readonly int $type,
		private readonly string $recipient,
		private readonly int $permissions,
		private readonly string $time,
	) {
	}

	/** @return array{id: int, object: string, initiator: string, type: int, recipient: string, permissions: int, password: bool, time: string, action: string} */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'object' => $this->object,
			'initiator' => $this->initiator,
			'type' => $this->type,
			'recipient' => $this->recipient,
			'permissions' => $this->permissions,
			'password' => false,
			'time' => $this->time,
			'action' => '',
		];
	}
}
