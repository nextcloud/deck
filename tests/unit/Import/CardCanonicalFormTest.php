<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

use Test\TestCase;

class CardCanonicalFormTest extends TestCase {
	/**
	 * The expected hashes come from Go: each value through encoding/json's
	 * json.Marshal, joined with "\n", SHA-256
	 */
	public function testHashMatchesGo(): void {
		$hash = CardCanonicalForm::hash(
			'1234567890',
			'bucket/1',
			"Plan <Q3> & \"launch\" \u{2028} ünïcødé 🚀",
			"Notes\r\n\n- [ ] one\n- [x] two\t\x01\x7f\x08\x0c\\ /path",
			8,
			null,
			new \DateTimeImmutable('2026-03-04T17:30:00+01:00'),
			new \DateTimeImmutable('2026-03-05T08:00:09.5Z'),
			['bob', 'Alice', 'älex'],
			['category2', 'category10', 'priority:urgent'],
			[42, 7, 1000],
		);

		$this->assertSame('64303043128b3af48f9572ccba442a4bb91519c7d5c963a35ac4913cdda24231', $hash);
	}

	public function testEmptyListsAreArrays(): void {
		$hash = CardCanonicalForm::hash('r', 's', 't', '', 0, null, null, null, [], [], []);

		$this->assertSame('3012ac82cf824f37fe7c854d91ccb0a6d0e9b6681606e5940cf9fa19b249b5ce', $hash);
	}

	public function testOrderOfListsDoesNotMatter(): void {
		$this->assertSame(
			CardCanonicalForm::hash('r', 's', 't', '', 0, null, null, null, ['a', 'b'], ['x', 'y'], [2, 1]),
			CardCanonicalForm::hash('r', 's', 't', '', 0, null, null, null, ['b', 'a'], ['y', 'x'], [1, 2]),
		);
	}

	public function testTimesAreUtcSeconds(): void {
		$this->assertSame('2026-01-02T02:04:05Z', CardCanonicalForm::time(new \DateTime('2026-01-02T03:04:05.999+01:00')));
		$this->assertNull(CardCanonicalForm::time(null));
	}
}
