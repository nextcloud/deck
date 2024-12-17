<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Search\Query;

class AQueryParameter {

	/** @var string */
	protected $field;
	/** @var int */
	protected $comparator;
	/** @var mixed */
	protected $value;
	
	public function getValue() {
		if (is_string($this->value) && mb_strlen($this->value) > 1) {
			$param = (mb_substr($this->value, 0, 1) === '"' && mb_substr($this->value, -1, 1) === '"') ? mb_substr($this->value, 1, -1): $this->value;
			return $param;
		}
		return $this->value;
	}
	
	public function getComparator(): int {
		return $this->comparator;
	}
}
