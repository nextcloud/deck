<?php

declare(strict_types=1);

/**
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

namespace OCA\Deck\Validators;

use OCA\Deck\Tests\unit\Validators\ValidatorTestBase;

class CardServiceValidatorTest extends ValidatorTestBase {
	public function setUp(): void {
		parent::setUpValidatorTest(CardServiceValidator::class);
	}

	public function testInvalidDueDateIsRejected(): void {
		$this->assertFail([
			'duedate' => '',
		]);
		$this->assertFail([
			'duedate' => 'not a date',
		]);
		$this->assertFail([
			'duedate' => '99999-01-01 00:00:00',
		]);
		$this->assertFail([
			'duedate' => '+1 day',
		]);
	}

	public function testValidDueDateIsAccepted(): void {
		$this->assertPass([
			'duedate' => '2026-01-01T01:00:00.000Z',
		]);
		$this->assertPass([
			'duedate' => '2026-01-01 00:00:00',
		]);
		$this->assertPass([
			'duedate' => '2026-01-01T00:00:00+00:00',
		]);
		$this->assertPass([
			'duedate' => '2026-01-01',
		]);
	}
}
