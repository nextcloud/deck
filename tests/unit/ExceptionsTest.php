<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Db;

use OCA\Deck\ArchivedItemException;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCA\Deck\StatusException;

class ExceptionsTest extends \Test\TestCase {
	public function testNoPermissionException() {
		$c = new \stdClass();
		$e = new NoPermissionException('not allowed', $c, 'mymethod');
		$this->assertEquals('stdClass#mymethod: not allowed', $e->getMessage());
		$this->assertEquals(403, $e->getStatus());
	}

	public function testNotFoundException() {
		$e = new NotFoundException('foo');
		$this->assertEquals('foo', $e->getMessage());
		$this->assertEquals(404, $e->getStatus());
	}

	public function testCardArchivedException() {
		$e = new ArchivedItemException('foo');
		$this->assertEquals('foo', $e->getMessage());
	}

	public function testInvalidAttachmentType() {
		$e = new InvalidAttachmentType('foo');
		$this->assertEquals('No matching IAttachmentService implementation found for type foo', $e->getMessage());
	}

	public function testStatusException() {
		$e = new StatusException('foo');
		$this->assertEquals('foo', $e->getMessage());
		$this->assertEquals(500, $e->getStatus());
	}
}
