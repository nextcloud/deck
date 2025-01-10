<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Search\Query;

class StringQueryParameter extends AQueryParameter {
	
	/** @var string */
	protected $value;
	
	public function __construct(string $field, int $comparator, string $value) {
		$this->field = $field;
		$this->comparator = $comparator;
		$this->value = $value;
	}
}
