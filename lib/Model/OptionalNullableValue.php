<?php
/**
 * @copyright Copyright (c) 2023 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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
