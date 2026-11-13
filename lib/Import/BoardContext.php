<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

use OCA\Deck\Db\Board;

/**
 * A board with the references of its imported stacks and labels that
 * still exist
 *
 * @internal
 */
final class BoardContext {
	/**
	 * @param array<string, int> $stackIds by ref
	 * @param array<string, int> $labelIds by ref
	 */
	public function __construct(
		public readonly Board $board,
		public readonly array $stackIds,
		public readonly array $labelIds,
	) {
	}

	/**
	 * The reference of a stack, or a stand-in for one the importer didn't write
	 */
	public function stackRef(int $stackId): string {
		$ref = array_search($stackId, $this->stackIds, true);
		return $ref === false ? 'deck:' . $stackId : (string)$ref;
	}

	/**
	 * The reference of a label, or a stand-in for one the importer didn't write
	 */
	public function labelRef(int $labelId): string {
		$ref = array_search($labelId, $this->labelIds, true);
		return $ref === false ? 'deck:' . $labelId : (string)$ref;
	}
}
