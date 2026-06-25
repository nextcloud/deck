<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Julius Härtl <jus@bitgrid.net>
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


namespace OCA\Deck\Tests\unit\Validators;

use OCA\Deck\BadRequestException;
use OCA\Deck\Validators\BaseValidator;

abstract class ValidatorTestBase extends \PHPUnit\Framework\TestCase {
	protected BaseValidator $validator;

	public function setUpValidatorTest($class = null): void {
		parent::setUp();

		$this->validator = new $class();
	}

	protected function assertPass($values) {
		self::assertTrue($this->check($values));
	}

	protected function assertFail($values) {
		self::assertFalse($this->check($values));
	}

	private function check($values) {
		try {
			$this->validator->check($values);
			return true;
		} catch (BadRequestException $e) {
			return false;
		}
	}
}
