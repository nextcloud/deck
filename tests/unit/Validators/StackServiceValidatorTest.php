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


namespace OCA\Deck\Validators;

use OCA\Deck\Tests\unit\Validators\ValidatorTestBase;

class StackServiceValidatorTest extends ValidatorTestBase {
	public function setUp(): void {
		parent::setUpValidatorTest(StackServiceValidator::class);
	}

	public function testTitle() {
		$this->assertPass([
			'title' => 'Short title',
		]);
		$this->assertPass([
			'title' => str_repeat('A', 100)
		]);
		$this->assertFail([
			'title' => str_repeat('A', 101)
		]);
		$this->assertFail([
			'title' => '',
		]);
		$this->assertFail([
			'title' => null,
		]);
	}

	public function testId() {
		$this->assertPass([ 'id' => 1234 ]);
		$this->assertPass([ 'id' => '1234' ]);
		$this->assertFail([ 'id' => 'a1234' ]);
		$this->assertFail([ 'id' => '' ]);
		$this->assertFail([ 'id' => null ]);
	}

	public function testBoardId() {
		$this->assertPass([ 'boardId' => 1234 ]);
		$this->assertPass([ 'boardId' => '1234' ]);
		$this->assertFail([ 'boardId' => 'a1234' ]);
		$this->assertFail([ 'boardId' => '' ]);
		$this->assertFail([ 'boardId' => null ]);
	}

	public function testOrder() {
		$this->assertPass([ 'order' => 1234 ]);
		$this->assertPass([ 'order' => '1234' ]);
		$this->assertFail([ 'order' => 'a1234' ]);
		$this->assertFail([ 'order' => '' ]);
		$this->assertFail([ 'order' => null ]);
	}
}
