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

use OC\AppFramework\DependencyInjection\DIContainer;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OC\AppFramework\Utility\SimpleContainer;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCA\Deck\Service\BoardService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IContainer;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\Deck\Db\AclMapper;

class SharingMiddlewareTest extends \PHPUnit_Framework_TestCase {

	private $sharingMiddleware;
	private $container;
	private $request;
	private $userSession;
	private $reflector;
	private $groupManager;
	private $aclMapper;
	private $boardService;

	public function setUp() {
		$this->container = new SimpleContainer();
		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()->getMock();
		$this->reflector = $this->getMockBuilder(ControllerMethodReflector::class)
			->disableOriginalConstructor()->getMock();
		$this->groupManager = $this->getMockBuilder(IGroupManager::class)
			->disableOriginalConstructor()->getMock();
		$this->aclMapper = $this->getMockBuilder(AclMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->boardService = $this->getMockBuilder(BoardService::class)
			->disableOriginalConstructor()->getMock();
		$this->sharingMiddleware = new SharingMiddleware(
			$this->container,
			$this->request,
			$this->userSession,
			$this->reflector,
			$this->groupManager,
			$this->aclMapper,
			$this->boardService
		);
	}

	public function testBeforeController() {
		$controller = $this->getMockBuilder(Controller::class)
			->disableOriginalConstructor()->getMock();
		$methodName = '';
	}


	public function dataAfterException() {
		return [
			[new NoPermissionException('No permission'), 401, 'No permission'],
			[new NotFoundException('Not found'), 404, 'Not found']
		];
	}
	/**
	 * @dataProvider dataAfterException
	 */
	public function testAfterException($exception, $status, $message) {
		$result = $this->sharingMiddleware->afterException('Foo', 'bar', $exception);
		$expected = new JSONResponse([
			"status" => $status,
			"message" => $message
		], $status);
		$this->assertEquals($expected, $result);

	}

}