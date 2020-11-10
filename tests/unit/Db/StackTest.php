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

class StackTest extends \Test\TestCase {
	private function createStack() {
		$board = new Stack();
		$board->setId(1);
		$board->setTitle("My Stack");
		$board->setBoardId(1);
		$board->setOrder(1);
		return $board;
	}
	public function testJsonSerialize() {
		$stack = $this->createStack();
		$this->assertEquals([
			'id' => 1,
			'title' => "My Stack",
			'order' => 1,
			'boardId' => 1,
			'deletedAt' => 0,
			'lastModified' => 0,
			'ETag' => $stack->getETag(),
		], $stack->jsonSerialize());
	}
	public function testJsonSerializeWithCards() {
		$cards = ["foo", "bar"];
		$stack = $this->createStack();
		$stack->setCards($cards);
		$this->assertEquals([
			'id' => 1,
			'title' => "My Stack",
			'order' => 1,
			'boardId' => 1,
			'cards' => ["foo", "bar"],
			'deletedAt' => 0,
			'lastModified' => 0,
			'ETag' => $stack->getETag(),
		], $stack->jsonSerialize());
	}
}
