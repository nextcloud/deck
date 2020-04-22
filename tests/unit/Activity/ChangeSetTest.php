<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Activity;

use PHPUnit\Framework\TestCase;

class ChangeSetTest extends TestCase {
	public function setUp(): void {
	}

	public function testChangeSetScalar() {
		$changeSet = new ChangeSet('A', 'B');
		$this->assertEquals('A', $changeSet->getBefore());
		$this->assertEquals('B', $changeSet->getAfter());
		$this->assertFalse($changeSet->getDiff());
		$changeSet->enableDiff();
		$this->assertTrue($changeSet->getDiff());
	}

	public function testChangeSetObject() {
		$a = new \stdClass;
		$a->data = 'A';
		$b = new \stdClass;
		$b->data = 'B';
		$changeSet = new ChangeSet($a, $b);
		$this->assertEquals('A', $changeSet->getBefore()->data);
		$this->assertEquals('B', $changeSet->getAfter()->data);
		$this->assertNotSame($a, $changeSet->getBefore());
		$this->assertNotSame($b, $changeSet->getAfter());
		$this->assertFalse($changeSet->getDiff());
		$changeSet->enableDiff();
		$this->assertTrue($changeSet->getDiff());
	}
}
