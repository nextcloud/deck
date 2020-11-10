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

use Test\TestCase;

class LabelTest extends TestCase {
	private function createLabel() {
		$label = new Label();
		$label->setId(1);
		$label->setTitle("My Label");
		$label->setColor("000000");
		return $label;
	}
	public function testJsonSerializeBoard() {
		$label = $this->createLabel();
		$label->setBoardId(123);
		$this->assertEquals([
			'id' => 1,
			'title' => 'My Label',
			'boardId' => 123,
			'cardId' => null,
			'lastModified' => null,
			'color' => '000000',
			'ETag' => $label->getETag(),
		], $label->jsonSerialize());
	}
	public function testJsonSerializeCard() {
		$label = $this->createLabel();
		$label->setCardId(123);
		$this->assertEquals([
			'id' => 1,
			'title' => 'My Label',
			'boardId' => null,
			'cardId' => 123,
			'lastModified' => null,
			'color' => '000000',
			'ETag' => $label->getETag(),
		], $label->jsonSerialize());
	}
}
