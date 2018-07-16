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
		$this->exampleStack['boardId'] = $this->exampleBoard['boardId'];
		$this->exampleStack['order'] = 0;
		$this->exampleStack['title'] = 'Example Stack From API';

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

	public function testGet() {
		$stack = new Stack();
		$stack->setId($this->exampleStack['id']);
		$stack->setBoardId($this->exampleStack['boardId']);
		$stack->setOrder($this->exampleStack['order']);		

		$this->stackService->expects($this->once())
			->method('find')
			->willReturn($stack);

		$this->request->expects($this->once())
			->method('getParam')
			->with('stackId')
			->willReturn($this->exampleStack['id']);	

		$expected = new DataResponse($stack, HTTP::STATUS_OK);
		$actual = $this->controller->get();
		$this->assertEquals($expected, $actual);
	}				

	public function testCreate() {

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['boardId']));

		$stack = new Stack();
		$stack->setId($this->exampleStack['id']);
		$stack->setBoardId($this->exampleStack['boardId']);
		$stack->setOrder($this->exampleStack['order']);
		$stack->setTitle($this->exampleStack['title']);

		$this->stackService->expects($this->once())
			->method('create')
			->willReturn($stack);

		$expected = new DataResponse($stack, HTTP::STATUS_OK);
		$actual = $this->controller->create($this->exampleStack['title'], $this->exampleStack['order']);
		$this->assertEquals($expected, $actual);
	}			
}