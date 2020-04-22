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

use OCA\Deck\Service\LabelService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class LabelControllerTest extends \Test\TestCase {

	/** @var Controller|\PHPUnit\Framework\MockObject\MockObject */
	private $controller;
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var LabelService|\PHPUnit\Framework\MockObject\MockObject */
	private $labelService;
	/** @var string */
	private $userId = 'user';

	public function setUp(): void {
		$this->request = $this->getMockBuilder(
			'\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->labelService = $this->getMockBuilder(
			'\OCA\Deck\Service\LabelService')
			->disableOriginalConstructor()
			->getMock();
		$this->controller = new LabelController(
			'deck',
			$this->request,
			$this->labelService
		);
	}


	public function testCreate() {
		$this->labelService->expects($this->once())
			->method('create')
			->with(1, 2, 3)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->create(1, 2, 3));
	}

	public function testUpdate() {
		$this->labelService->expects($this->once())
			->method('update')
			->with(1, 2, 3)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->update(1, 2, 3));
	}

	public function testDelete() {
		$this->labelService->expects($this->once())
			->method('delete')
			->with(123)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->delete(123));
	}
}
