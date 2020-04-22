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

namespace OCA\Deck\Controller;

use OCA\Deck\Service\AttachmentService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class AttachmentControllerTest extends \Test\TestCase {

	/** @var Controller|\PHPUnit\Framework\MockObject\MockObject */
	private $controller;
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var AttachmentService|\PHPUnit\Framework\MockObject\MockObject */
	private $attachmentService;
	/** @var string */
	private $userId = 'user';

	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->attachmentService = $this->createMock(AttachmentService::class);
		$this->controller = new AttachmentController(
			'deck',
			$this->request,
			$this->attachmentService,
			$this->userId
		);
	}

	public function testGetAll() {
		$this->attachmentService->expects($this->once())->method('findAll')->with(1);
		$this->controller->getAll(1);
	}

	public function testDisplay() {
		$this->attachmentService->expects($this->once())->method('display')->with(1, 2);
		$this->controller->display(1, 2);
	}

	public function testCreate() {
		$this->request->expects($this->exactly(2))
			->method('getParam')
			->will($this->onConsecutiveCalls('type', 'data'));
		$this->attachmentService->expects($this->once())
			->method('create')
			->with(1, 'type', 'data')
			->willReturn(1);
		$this->assertEquals(1, $this->controller->create(1));
	}

	public function testUpdate() {
		$this->request->expects($this->exactly(1))
			->method('getParam')
			->will($this->onConsecutiveCalls('data'));
		$this->attachmentService->expects($this->once())
			->method('update')
			->with(1, 2, 'data')
			->willReturn(1);
		$this->assertEquals(1, $this->controller->update(1, 2));
	}


	public function testDelete() {
		$this->attachmentService->expects($this->once())
			->method('delete')
			->with(123, 234)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->delete(123, 234));
	}

	public function testRestore() {
		$this->attachmentService->expects($this->once())
			->method('restore')
			->with(123, 234)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->restore(123, 234));
	}
}
