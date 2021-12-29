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

use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\CardService;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CardControllerTest extends TestCase {

	/** @var CardController|MockObject */
	private $controller;
	/** @var IRequest|MockObject */
	private $request;
	/** @var CardService|MockObject */
	private $cardService;
	/** @var AssignmentService|MockObject */
	private $assignmentService;
	/** @var string */
	private $userId = 'user';


	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->cardService = $this->createMock(CardService::class);
		$this->assignmentService = $this->createMock(AssignmentService::class);
		$this->controller = new CardController(
			'deck',
			$this->request,
			$this->cardService,
			$this->assignmentService,
			$this->userId
		);
	}

	public function testRead() {
		$this->cardService->expects($this->once())
			->method('find')
			->with(123)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->read(123));
	}

	public function testCreate() {
		$this->cardService->expects($this->once())
			->method('create')
			->with('foo', 1, 'text', 3, $this->userId)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->create('foo', 1, 'text', 3));
	}

	public function testUpdate() {
		$this->cardService->expects($this->once())
			->method('update')
			->with(1, 'title', 3, 'text', $this->userId, 'foo', 5, '2017-01-01 00:00:00')
			->willReturn(1);
		$this->assertEquals(1, $this->controller->update(1, 'title', 3, 'text', 5, 'foo', '2017-01-01 00:00:00', null));
	}

	public function testDelete() {
		$this->cardService->expects($this->once())
			->method('delete')
			->with(123)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->delete(123));
	}

	public function testArchive() {
		$this->cardService->expects($this->once())->method('archive')->willReturn(true);
		$this->controller->archive(1);
	}
	public function testUnarchive() {
		$this->cardService->expects($this->once())->method('unarchive');
		$this->controller->unarchive(1);
	}
	public function testAssignLabel() {
		$this->cardService->expects($this->once())->method('assignLabel');
		$this->controller->assignLabel(1, 2);
	}
	public function testRemoveLabel() {
		$this->cardService->expects($this->once())->method('removeLabel');
		$this->controller->removeLabel(1, 2);
	}

	public function testReorder() {
		$this->cardService->expects($this->once())->method('reorder');
		$this->controller->reorder(1, 2, 3);
	}

	public function testRename() {
		$this->cardService->expects($this->once())->method('rename');
		$this->controller->rename(1, 'test');
	}

	public function testAssignUser() {
		$this->assignmentService->expects($this->once())->method('assignUser');
		$this->controller->assignUser(1, 'admin');
	}

	public function testUnssignUser() {
		$this->assignmentService->expects($this->once())->method('unassignUser');
		$this->controller->unassignUser(1, 'admin');
	}
}
