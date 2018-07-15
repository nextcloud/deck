<?php
/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\StackService;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Stack;

class StackApiControllerTest extends \Test\TestCase {

	private $appName = 'deck';
	private $userId = 'admin';
	private $controller;	
	private $boardService;
	private $stackService;
	private $exampleStack;
	private $exampleBoard;

	public function setUp() {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->stackService = $this->createMock(StackService::class);		

		$this->exampleStack['id'] = 345;
		$this->exampleStack['boardId'] = 245;
		$this->exampleStack['order'] = 0;		

		$this->exampleBoard['boardId'] = '89';

		$this->controller = new StackApiController(
			$this->appName,
			$this->request,
			$this->stackService,
			$this->boardService
		);
	}

	public function testIndex() {
		$stack = new Stack();
		$stack->setId($this->exampleStack['id']);
		$stack->setBoardId($this->exampleStack['boardId']);
		$stack->setOrder($this->exampleStack['order']);		
		$stacks = [$stack];

		$board = new Board();
		$board->setId($this->exampleBoard['boardId']);
		$this->boardService->expects($this->once())
			->method('find')
			->willReturn($board);

		$this->stackService->expects($this->once())
			->method('findAll')
			->willReturn($stacks);

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['boardId']));

		$expected = new DataResponse($stacks, HTTP::STATUS_OK);
		$actual = $this->controller->index();
		$this->assertEquals($expected, $actual);
	}

	public function testIndexBadBoardId() {
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue('bad board id'));

		$expected = new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->index();
		$this->assertEquals($expected, $actual);
	}

	public function testIndexBoardNotFound() {
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue(689));

		$expected = new DataResponse('board not found', HTTP::STATUS_NOT_FOUND);
		$actual = $this->controller->index();
		$this->assertEquals($expected, $actual);
	}

}