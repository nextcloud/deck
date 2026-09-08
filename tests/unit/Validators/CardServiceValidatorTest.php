<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Validators;

use OCA\Deck\Tests\unit\Validators\ValidatorTestBase;

class CardServiceValidatorTest extends ValidatorTestBase {
	public function setUp(): void {
		parent::setUpValidatorTest(CardServiceValidator::class);
	}

	public function testTitle() {
		$this->assertPass([ 'title' => 'Short title' ]);
		$this->assertPass([ 'title' => str_repeat('A', 255) ]);
		$this->assertFail([ 'title' => str_repeat('A', 256) ]);
		$this->assertFail([ 'title' => '' ]);
		$this->assertFail([ 'title' => null ]);
	}

	public function testId() {
		$this->assertPass([ 'id' => 1234 ]);
		$this->assertPass([ 'id' => '1234' ]);
		$this->assertFail([ 'id' => 'a1234' ]);
		$this->assertFail([ 'id' => '' ]);
		$this->assertFail([ 'id' => null ]);
	}

	public function testDuedateAcceptsSupportedFormats() {
		// ISO-8601 as sent by the web frontend
		$this->assertPass([ 'duedate' => '2019-12-24T19:29:30.000Z' ]);
		$this->assertPass([ 'duedate' => '2019-12-24T19:29:30.123456Z' ]);
		// ISO-8601 as documented in docs/API.md
		$this->assertPass([ 'duedate' => '2019-12-24T19:29:30+00:00' ]);
		$this->assertPass([ 'duedate' => '2019-12-24T19:29:30Z' ]);
		// Formats used by API consumers and the integration tests
		$this->assertPass([ 'duedate' => '2020-12-12 13:37:00' ]);
		$this->assertPass([ 'duedate' => '2020-12-12' ]);
		$this->assertPass([ 'duedate' => '3000-12-12' ]);
		// A leap day is a valid date
		$this->assertPass([ 'duedate' => '2020-02-29T00:00:00.000Z' ]);
	}

	public function testDuedateCanBeUnset() {
		$this->assertPass([ 'duedate' => null ]);
		$this->assertPass([ 'duedate' => '' ]);
	}

	public function testDuedateRejectsOutOfRangeYears() {
		$this->assertFail([ 'duedate' => '20250-12-09T04:30:00.000Z' ]);
		$this->assertFail([ 'duedate' => '20250-12-09 04:30:00' ]);
		$this->assertFail([ 'duedate' => '99999-01-01T00:00:00Z' ]);
		// \DateTime would silently read this as 2005-01-01 12:34
		$this->assertFail([ 'duedate' => '12345-01-01' ]);
	}

	public function testDuedateYearBounds() {
		$this->assertPass([ 'duedate' => '1000-01-01' ]);
		$this->assertPass([ 'duedate' => '9999-12-31T23:59:59' ]);
		$this->assertFail([ 'duedate' => '0999-12-31' ]);
		$this->assertFail([ 'duedate' => '10000-01-01' ]);
	}

	public function testDuedateRejectsInvalidDates() {
		$this->assertFail([ 'duedate' => '2025-13-45T99:99:99.000Z' ]);
		$this->assertFail([ 'duedate' => '2025-02-30T00:00:00.000Z' ]);
		$this->assertFail([ 'duedate' => 'not-a-date' ]);
		$this->assertFail([ 'duedate' => '0' ]);
		$this->assertFail([ 'duedate' => 1234 ]);
	}

	public function testStartdate() {
		$this->assertPass([ 'startdate' => '2019-12-24T19:29:30+00:00' ]);
		$this->assertPass([ 'startdate' => null ]);
		$this->assertFail([ 'startdate' => '20250-12-09T04:30:00.000Z' ]);
	}
}
