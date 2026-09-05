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

use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Label;
use OCA\Deck\Service\LabelService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;

class LabelApiControllerTest extends \Test\TestCase {
	private LabelApiController $controller;
	private IRequest&MockObject $request;
	private LabelService&MockObject $labelService;
	private ChangeHelper&MockObject $changeHelper;
	private string $userId = 'admin';
	private array $exampleLabel = [
		'id' => 123
	];

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->labelService = $this->createMock(LabelService::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);
		$this->controller = new LabelApiController(
			'deck',
			$this->request,
			$this->labelService,
			$this->changeHelper
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

		$this->changeHelper->expects($this->once())
			->method('getEtag')
			->with(ChangeHelper::TYPE_LABEL, $this->exampleLabel['id'])
			->willReturn('');

		$expected = new DataResponse($label, HTTP::STATUS_OK);
		$expected->setETag($label->getETag());
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
			->method('find')
			->with($this->exampleLabel['id'])
			->willReturn($label);

		$this->changeHelper->expects($this->once())
			->method('checkIfMatch')
			->with(ChangeHelper::TYPE_LABEL, $this->exampleLabel['id'], $label->getETag());

		$this->labelService->expects($this->once())
			->method('update')
			->with($this->exampleLabel['id'], 'title', '000000')
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
			->method('find')
			->with($this->exampleLabel['id'])
			->willReturn($label);

		$this->changeHelper->expects($this->once())
			->method('checkIfMatch')
			->with(ChangeHelper::TYPE_LABEL, $this->exampleLabel['id'], $label->getETag());

		$this->labelService->expects($this->once())
			->method('delete')
			->with($this->exampleLabel['id'])
			->willReturn($label);

		$expected = new DataResponse($label, HTTP::STATUS_OK);
		$actual = $this->controller->delete();
		$this->assertEquals($expected, $actual);
	}
}
