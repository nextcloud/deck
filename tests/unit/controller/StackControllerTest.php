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

namespace OCA\Deck\Controller;

use OCA\Deck\Service\StackService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class StackControllerTest extends \Test\TestCase {

	/** @var Controller|\PHPUnit\Framework\MockObject\MockObject */
	private $controller;
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var StackService|\PHPUnit\Framework\MockObject\MockObject */
	private $stackService;
	/** @var string */
	private $userId = 'user';

	public function setUp(): void {
		$this->request = $this->getMockBuilder(
			'\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->stackService = $this->getMockBuilder(
			'\OCA\Deck\Service\StackService')
			->disableOriginalConstructor()
			->getMock();
		$this->controller = new StackController(
			'deck',
			$this->request,
			$this->stackService,
			$this->userId
		);
	}

	public function testIndex() {
		$this->stackService->expects($this->once())->method('findAll');
		$this->controller->index(1);
	}

	public function testArchived() {
		$this->stackService->expects($this->once())->method('findAllArchived');
		$this->controller->archived(1);
	}

	public function testCreate() {
		$this->stackService->expects($this->once())
			->method('create')
			->with(1, 2, 3)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->create(1, 2, 3));
	}

	public function testUpdate() {
		$this->stackService->expects($this->once())
			->method('update')
			->with(1, 2, 3, 4)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->update(1, 2, 3, 4, null));
	}

	public function testReorder() {
		$this->stackService->expects($this->once())
			->method('reorder')
			->with(1, 2)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->reorder(1, 2));
	}

	public function testDelete() {
		$this->stackService->expects($this->once())
			->method('delete')
			->with(123)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->delete(123));
	}
}
