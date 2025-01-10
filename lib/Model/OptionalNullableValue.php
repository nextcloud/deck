<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Model;

/**
 * This is a helper abstraction to allow usage of optional parameters
 * which hold a nullable value. The actual null value of the parameter
 * is used to indicate if it has been set or not. The containing value
 * will then still allow having null as a value
 *
 * Example use case: Have a nullable database column,
 * but only update it if it is passed
 *
 * @template T
 */
class OptionalNullableValue {

	/** @var ?T */
	private mixed $value;

	/** @param ?T $value */
	public function __construct(mixed $value) {
		$this->value = $value;
	}

	/** @return ?T */
	public function getValue(): mixed {
		return $this->value;
	}

}
