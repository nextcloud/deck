<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\ShareReview\Sources;

/**
 * Runtime stub for the optional grc_sharereview app.
 * Only loaded when the real app is not installed.
 */
interface ISource {
	public function getName(): string;

	public function getShares(): array;

	public function deleteShare(string $shareId): bool;
}
