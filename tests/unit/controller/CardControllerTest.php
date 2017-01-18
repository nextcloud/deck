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

use OCA\Deck\Db\Acl;
use OCA\Deck\Service\CardService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class CardControllerTest extends \PHPUnit_Framework_TestCase {

    /** @var Controller|\PHPUnit_Framework_MockObject_MockObject */
	private $controller;
    /** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
    private $request;
    /** @var CardService|\PHPUnit_Framework_MockObject_MockObject */
	private $cardService;
	/** @var string */
	private $userId = 'user';

	public function setUp() {
		$this->request = $this->getMockBuilder(
			'\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->cardService = $this->getMockBuilder(
			'\OCA\Deck\Service\CardService')
			->disableOriginalConstructor()
			->getMock();
		$this->controller = new CardController(
			'deck',
			$this->request,
			$this->cardService,
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
			->with(1, 'title', 3, 'text', 5, 'foo', $this->userId)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->update(1, 'title', 3, 'text', 5, 'foo'));
	}

	public function testDelete() {
		$this->cardService->expects($this->once())
			->method('delete')
			->with(123)
			->willReturn(1);
		$this->assertEquals(1, $this->controller->delete(123));
	}

	public function testArchive() {
	    $this->cardService->expects($this->once())->method('archive');
	    $this->cardService->archive(1);
    }
    public function testUnarchive() {
        $this->cardService->expects($this->once())->method('unarchive');
        $this->cardService->unarchive(1);
    }
    public function testAssignLabel() {
        $this->cardService->expects($this->once())->method('assignLabel');
        $this->cardService->assignLabel(1,2);
    }
    public function testRemoveLabel() {
        $this->cardService->expects($this->once())->method('removeLabel');
        $this->cardService->removeLabel(1,2);
    }

}
