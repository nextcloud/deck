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

use OCA\Deck\Db\Label;
use OCA\Deck\Service\LabelService;

class LabelApiControllerTest extends \Test\TestCase {
	private $controller;
	private $request;
	private $labelService;
	private $userId = 'admin';
	private $exampleLabel = [
		'id' => 123
	];

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->labelService = $this->createMock(LabelService::class);
		$this->exampleLabel['id'];
		$this->controller = new LabelApiController(
			'deck',
			$this->request,
			$this->labelService,
			$this->userId
		);
	}

	public function testGet() {
		$label = new Label();
		$label->setId($this->exampleLabel['id']);

		$this->request->expects($this->once())
			->method('getParam')
			->with('labelId')
			->will($this->returnValue($this->exampleLabel['id']));

		$this->labelService->expects($this->once())
			->method('find')
			->willReturn($label);

		$expected = new DataResponse($label, HTTP::STATUS_OK);
		$actual = $this->controller->get();
		$this->assertEquals($expected, $actual);
	}

	public function testCreate() {
		$label = new Label();
		$label->setId($this->exampleLabel['id']);

		$this->request->expects($this->once())
			->method('getParam')
			->with('boardId')
			->will($this->returnValue(1));

		$this->labelService->expects($this->once())
			->method('create')
			->willReturn($label);

		$expected = new DataResponse($label, HTTP::STATUS_OK);
		$actual = $this->controller->create('title', '000000');
		$this->assertEquals($expected, $actual);
	}

	public function testUpdate() {
		$label = new Label();
		$label->setId($this->exampleLabel['id']);

		$this->request->expects($this->once())
			->method('getParam')
			->with('labelId')
			->will($this->returnValue($this->exampleLabel['id']));

		$this->labelService->expects($this->once())
			->method('update')
			->will($this->returnValue($label));

		$expected = new DataResponse($label, HTTP::STATUS_OK);
		$actual = $this->controller->update('title', '000000');
		$this->assertEquals($expected, $actual);
	}

	public function testDelete() {
		$label = new Label();
		$label->setId($this->exampleLabel['id']);

		$this->request->expects($this->once())
			->method('getParam')
			->with('labelId')
			->will($this->returnValue($this->exampleLabel['id']));

		$this->labelService->expects($this->once())
			->method('delete')
			->willReturn($label);

		$expected = new DataResponse($label, HTTP::STATUS_OK);
		$actual = $this->controller->delete();
		$this->assertEquals($expected, $actual);
	}
}
