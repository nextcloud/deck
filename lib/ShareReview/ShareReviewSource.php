<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\ShareReview;

use OCA\ShareReview\Sources\ISource;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class ShareReviewSource implements ISource {

	private const ACL_TABLE = 'deck_board_acl';
	private const BOARDS_TABLE = 'deck_boards';
	private const PERMISSION_MANAGE = 32;

	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Deck';
	}

	public function getShares(): array {
		return [];
	}

	public function deleteShare(string $shareId): bool {
		return false;
	}
}
