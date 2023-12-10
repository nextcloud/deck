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

class MyRelationalEntity extends RelationalEntity {
	protected $foo;
}

class RelationalEntityTest extends \Test\TestCase {
	public function testRelation() {
		$entity = new MyRelationalEntity();
		$entity->setFoo(null);
		$entity->addRelation('foo');
		$entity->setFoo('test');
		$this->assertEquals([], $entity->getUpdatedFields());
	}
	
	public function testWithoutRelation() {
		$entity = new MyRelationalEntity();
		$entity->setFoo(null);
		$entity->setFoo('test');
		$this->assertEquals(['foo' => true], $entity->getUpdatedFields());
	}

	public function testJsonSerialize() {
		$entity = new RelationalEntity();
		$entity->setId(123);
		$json = [
			'id' => 123,
		];
		$this->assertEquals($json, $entity->jsonSerialize());
	}
}
