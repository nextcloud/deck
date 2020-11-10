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

use OCA\Deck\Service\AssignmentService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

use OCA\Deck\Db\Card;
use OCA\Deck\Service\CardService;

class CardApiControllerTest extends \Test\TestCase {
	private $controller;
	private $request;
	private $cardService;
	private $userId = 'admin';
	private $cardExample;
	private $stackExample;
	private $assignmentService;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->cardService = $this->createMock(CardService::class);
		$this->assignmentService = $this->createMock(AssignmentService::class);

		$this->cardExample['id'] = 1;
		$this->stackExample['id'] = 1;

		$this->controller = new CardApiController(
			$appName = 'deck',
			$this->request,
			$this->cardService,
			$this->assignmentService,
			$this->userId
		);
	}

	public function testGet() {
		$card = new Card();
		$card->setId($this->cardExample['id']);

		$this->request->expects($this->once())
			->method('getParam')
			->with('cardId')
			->will($this->returnValue($this->cardExample['id']));

		$this->cardService->expects($this->once())
			->method('find')
			->willReturn($card);

		$expected = new DataResponse($card, HTTP::STATUS_OK);
		$expected->setETag($card->getETag());
		$actual = $this->controller->get();
		$this->assertEquals($expected, $actual);
	}

	public function testCreate() {
		$card = new Card();
		$card->setId($this->cardExample['id']);
		$card->setStackId($this->stackExample['id']);

		$this->request->expects($this->once())
			->method('getParam')
			->with('stackId')
			->willReturn($this->stackExample['id']);

		$this->cardService->expects($this->once())
			->method('create')
			->willReturn($card);

		$expected = new DataResponse($card, HTTP::STATUS_OK);
		$actual = $this->controller->create('title');
		$this->assertEquals($expected, $actual);
	}

	public function testUpdate() {
		$card = new Card();
		$card->setId($this->cardExample['id']);
		$card->setStackId($this->stackExample['id']);

		$this->request->expects($this->exactly(2))
			->method('getParam')
			->withConsecutive(
				['cardId'],
				['stackId']
			)->willReturnonConsecutiveCalls(
				$this->cardExample['id'],
				$this->stackExample['id']);

		$this->cardService->expects($this->once())
				->method('update')
				->willReturn($card);

		$expected = new DataResponse($card, HTTP::STATUS_OK);
		$actual = $this->controller->update('title', 'plain', 0, 'description', $this->userId, null);
		$this->assertEquals($expected, $actual);
	}

	public function testDelete() {
		$card = new Card();
		$card->setId($this->cardExample['id']);

		$this->request->expects($this->once())
			->method('getParam')
			->with('cardId')
			->will($this->returnValue($this->cardExample['id']));

		$this->cardService->expects($this->once())
			->method('delete')
			->willReturn($card);

		$expected = new DataResponse($card, HTTP::STATUS_OK);
		$actual = $this->controller->delete();
		$this->assertEquals($expected, $actual);
	}
}
