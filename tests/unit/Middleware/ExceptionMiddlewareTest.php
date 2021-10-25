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

namespace OCA\Deck\Middleware;

use OCA\Deck\Controller\BoardController;
use OCA\Deck\Controller\PageController;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\ILogger;
use OCP\IConfig;
use OCP\IRequest;

class ExceptionMiddlewareTest extends \Test\TestCase {

	/** @var ILogger */
	private $logger;
	/** @var IConfig */
	private $config;
	private $controller;
	private $exceptionMiddleware;

	public function setUp(): void {
		$this->logger = $this->createMock(ILogger::class);
		$this->config = $this->createMock(IConfig::class);
		$this->request = $this->createMock(IRequest::class);
		$this->controller = $this->createMock(Controller::class);
		$this->exceptionMiddleware = new ExceptionMiddleware(
			$this->logger,
			$this->config,
			$this->request
		);
	}


	public function dataAfterException() {
		return [
			[new NoPermissionException('No permission'), 403, 'No permission'],
			[new NotFoundException('Not found'), 404, 'Not found']
		];
	}
	/**
	 * @dataProvider dataAfterException
	 */
	public function testAfterException($exception, $status, $message) {
		$result = $this->exceptionMiddleware->afterException($this->controller, 'bar', $exception);
		$expected = new JSONResponse([
			"status" => $status,
			"message" => $message
		], $status);
		$this->assertEquals($expected, $result);
	}

	public function testAfterExceptionNoController() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('failed hard');
		$pageController = $this->createMock(PageController::class);
		$result = $this->exceptionMiddleware->afterException($pageController, 'bar', new \Exception('failed hard'));
	}

	public function testAfterExceptionFail() {
		$this->request->expects($this->any())->method('getId')->willReturn('abc123');
		// BoardService $boardService, PermissionService $permissionService, $userId
		$boardController = new BoardController('deck', $this->createMock(IRequest::class), $this->createMock(BoardService::class), $this->createMock(PermissionService::class), 'admin');
		$result = $this->exceptionMiddleware->afterException($boardController, 'bar', new \Exception('other exception message'));
		$this->assertEquals('Internal server error: Please contact the server administrator if this error reappears multiple times, please include the request ID "abc123" below in your report.', $result->getData()['message']);
		$this->assertEquals(500, $result->getData()['status']);
	}
}
