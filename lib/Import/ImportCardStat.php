<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * What Deck holds for one card reference
 *
 * @since 2.0.0
 */
final class ImportCardStat implements \JsonSerializable {
	/**
	 * @param bool $exists the card is on the board and the importer didn't remove it
	 * @param bool $changed somebody changed, archived or deleted the card in Deck since the import
	 */
	public function __construct(
		public readonly string $ref,
		public readonly bool $exists,
		public readonly ?int $cardId = null,
		public readonly ?string $sha256 = null,
		public readonly bool $changed = false,
	) {
	}

	/**
	 * @return array{ref: string, exists: bool, card_id?: int, sha256?: string, changed: bool}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		$json = ['ref' => $this->ref, 'exists' => $this->exists];
		if ($this->cardId !== null) {
			$json['card_id'] = $this->cardId;
		}
		if ($this->sha256 !== null) {
			$json['sha256'] = $this->sha256;
		}
		$json['changed'] = $this->changed;
		return $json;
	}
}
