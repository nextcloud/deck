<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *  
 */

namespace OCA\Deck\Cron;

use OCA\Deck\Db\BoardMapper;

class DeleteCronTest extends \Test\TestCase {

	/** @var BoardMapper|\PHPUnit_Framework_MockObject_MockObject */
	protected $boardMapper;
	/** @var DeleteCron */
	protected $deleteCron;

	public function setUp() {
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->deleteCron = new DeleteCron($this->boardMapper);
	}

	public function testDeleteCron() {
		$this->boardMapper->expects($this->once())
			->method('findToDelete')
			->willReturn([1, 2, 3, 4]);
		$this->boardMapper->expects($this->at(0))
			->method('delete')
			->with(1);
		$this->boardMapper->expects($this->at(1))
			->method('delete')
			->with(2);
		$this->boardMapper->expects($this->at(2))
			->method('delete')
			->with(3);
		$this->boardMapper->expects($this->at(3))
			->method('delete')
			->with(4);
		$this->invokePrivate($this->deleteCron, 'run', null);
	}
}