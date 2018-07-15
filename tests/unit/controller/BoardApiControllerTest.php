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
use OCA\Deck\Db\Board;

class BoardApiControllerTest extends \Test\TestCase {

	private $appName = 'deck';
	private $userId = 'admin';
	private $controller;
	private $boardService;
	private $exampleBoard;
	private $deniedBoard;

	public function setUp() {
		parent::setUp();					
		$this->request = $this->createMock(IRequest::class);
		$this->boardService = $this->createMock(BoardService::class);

		$this->controller = new BoardApiController(
			$this->appName,
			$this->request,
			$this->boardService,
			$this->userId
		);

		$this->exampleBoard['id'] = 1;
		$this->exampleBoard['title'] = 'titled';
		$this->exampleBoard['color'] = '000000';

		$this->deniedBoard['id'] = 2;
		$this->deniedBoard['owner'] = 'someone else';
		$this->deniedBoard['title'] = 'titled';
		$this->deniedBoard['color'] = '000000';
	}

	public function testIndex() {
		$board = new Board();
		$board->setId('1');
		$board->setTitle('test');
		$board->setOwner($this->userId);
		$board->setColor('000000');
		$boards = [$board];
		$this->boardService->expects($this->once())
			->method('findAll')
			->willReturn($boards);

		$expected = new DataResponse($boards, HTTP::STATUS_OK);
		$actual = $this->controller->index();

		$this->assertEquals($expected, $actual);
	}
	
	public function testGet() {
		$boardId = 25;
		$board = new Board();
		$board->setId($boardId);
		$this->boardService->expects($this->once())
			->method('find')
			->willReturn($board);

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($boardId));

		$expected = new DataResponse($board, HTTP::STATUS_OK);
		$actual = $this->controller->get();
		$this->assertEquals($expected, $actual);
	}	

	public function testGetBadRequest() {

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue('hello'));

		$expected = new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->get();

		$this->assertEquals($expected, $actual);
	}

	public function testGetNotFound() {
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue('999'));

		$expected = new DataResponse('board not found', HTTP::STATUS_NOT_FOUND);
		$actual = $this->controller->get();

		$this->assertEquals($expected, $actual);
	}
	
	public function testCreate() {
		$board = new Board();
		$board->setId($this->exampleBoard['id']);
		$board->setTitle($this->exampleBoard['title']);
		$board->setColor($this->exampleBoard['color']);
		$this->boardService->expects($this->once())
			->method('create')
			->willReturn($board);
		
		$expected = new DataResponse($board, HTTP::STATUS_OK);
		$actual = $this->controller->create($this->exampleBoard['title'], $this->exampleBoard['color']);
		$this->assertEquals($expected, $actual);
	}

	public function testCreateBadTitle() {		
		$expected = new DataResponse('title must be provided', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->create(null, $this->exampleBoard['color']);
		$this->assertEquals($expected, $actual);
	}
	
	public function testCreateBadColor() {		
		$expected = new DataResponse('color must be provided', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->create($this->exampleBoard['title'], null);
		$this->assertEquals($expected, $actual);
	}
	
	public function testUpdate() {
		$board = new Board();
		$board->setId($this->exampleBoard['id']);
		$board->setTitle($this->exampleBoard['title']);
		$board->setColor($this->exampleBoard['color']);
		$this->boardService->expects($this->once())
			->method('update')
			->willReturn($board);

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['id']));

		$expected = new DataResponse($board, HTTP::STATUS_OK);
		$actual = $this->controller->update($this->exampleBoard['title'], $this->exampleBoard['color']);
		$this->assertEquals($expected, $actual);		
	}

	public function testUpdateBadId() {		
		$expected = new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->update($this->exampleBoard['title'], $this->exampleBoard['color']);
		$this->assertEquals($expected, $actual);
	}

	public function testUpdateBadArchived() {
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['id']));			

		$expected = new DataResponse('archived must be a boolean', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->update($this->exampleBoard['title'], $this->exampleBoard['color'], 'Not a boolean value');
		$this->assertEquals($expected, $actual);
	}

	public function testUpdateBadTitle() {
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['id']));			

		$expected = new DataResponse('title must be provided', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->update(null, $this->exampleBoard['color']);
		$this->assertEquals($expected, $actual);
	}

	public function testUpdateBadColor() {
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['id']));			

		$expected = new DataResponse('color must be provided', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->update($this->exampleBoard['title'], null);
		$this->assertEquals($expected, $actual);
	}

	public function testUpdateBoardNotFound() {
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['id']));			

		$expected = new DataResponse('board not found', HTTP::STATUS_NOT_FOUND);
		$actual = $this->controller->update($this->exampleBoard['title'], $this->exampleBoard['color']);
		$this->assertEquals($expected, $actual);
	}

	// TODO: Write testDelete()
	public function testDelete() {		

		$board = new Board();
		$board->setId($this->exampleBoard['id']);
		$board->setTitle($this->exampleBoard['title']);
		$board->setColor($this->exampleBoard['color']);
		$this->boardService->expects($this->once())
			->method('delete')
			->willReturn($board);
		
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['id']));

		$expected = new DataResponse($board, HTTP::STATUS_OK);
		$actual = $this->controller->delete();

		$this->assertEquals($expected, $actual);
	}

	public function testDeleteBadId() {

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue('bad id'));

		$expected = new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->delete();

		$this->assertEquals($expected, $actual);
	}

	public function testDeleteNotFound() {
		
		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue('85'));
		
		$expected = new DataResponse('board not found', HTTP::STATUS_NOT_FOUND);
		$actual = $this->controller->delete();

		$this->assertEquals($expected, $actual);
	}	
	
	public function testUndoDelete() {
		$board = new board();
		$board->setId($this->exampleBoard['id']);
		$board->setTitle($this->exampleBoard['title']);
		$board->setColor($this->exampleBoard['color']);
		$this->boardService->expects($this->once())
			->method('deleteUndo')
			->willReturn($board);

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue($this->exampleBoard['id']));

		$expected = new DataResponse($board, HTTP::STATUS_OK);
		$actual = $this->controller->undoDelete();
		$this->assertEquals($expected, $actual);
	}

	public function testUndoDeleteBadId() {		

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue('bad id'));

		$expected = new DataResponse('board id must be a number', HTTP::STATUS_BAD_REQUEST);
		$actual = $this->controller->undoDelete();
		$this->assertEquals($expected, $actual);
	}

	public function testUndoDeleteNotFound() {

		$this->request->expects($this->any())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue(189));

		$expected = new DataResponse('board not found', HTTP::STATUS_NOT_FOUND);
		$actual = $this->controller->undoDelete();
		$this->assertEquals($expected, $actual);
	}
}