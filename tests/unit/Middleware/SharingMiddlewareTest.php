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
use OCA\Deck\Db\DeckMapper;
use OCA\Deck\Db\IPermissionMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IContainer;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCA\Deck\Db\AclMapper;

class SharingMiddlewareTest extends \PHPUnit_Framework_TestCase {

	private $sharingMiddleware;
	private $container;
	private $request;
	private $userSession;
	private $reflector;
	private $permissionService;

	public function setUp() {
		$this->container = $this->getMockBuilder(IContainer::class)
			->disableOriginalConstructor()->getMock();
		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()->getMock();
		$this->reflector = new ControllerMethodReflector();
		//$this->getMockBuilder(ControllerMethodReflector::class)
		//	->disableOriginalConstructor()->getMock();
		$this->permissionService = $this->getMockBuilder(PermissionService::class)
			->disableOriginalConstructor()->getMock();
		$this->sharingMiddleware = new SharingMiddleware(
			$this->container,
			$this->request,
			$this->userSession,
			$this->reflector,
			$this->permissionService
		);
	}

	public function dataBeforeController() {
		return [
			['GET', '\OCA\Deck\Controller\PageController', 'index', null, true],
			['GET', '\OCA\Deck\Controller\BoardController', 'index', null, true],
			['GET', '\OCA\Deck\Controller\BoardController', 'read', true, true],
			['GET', '\OCA\Deck\Controller\BoardController', 'read', false, true, NoPermissionException::class],
			['GET', '\OCA\Deck\Controller\CardController', 'read', false, true, NoPermissionException::class],
			['POST', '\OCA\Deck\Controller\CardController', 'reorder', false, true, NoPermissionException::class],
		];
	}

	/**
	 * @dataProvider dataBeforeController
	 * @param $controllerClass
	 * @param $methodName
	 */
	public function testBeforeController($method, $controllerClass, $methodName, $getPermission, $success, $exception=null) {
		$controller = $this->getMockBuilder($controllerClass)
			->disableOriginalConstructor()->getMock();
		$mapper = $this->getMockBuilder(IPermissionMapper::class)
			->disableOriginalConstructor()->getMock();
		$mapper->expects($this->any())->method('findBoardId')->willReturn(123);
		$mapper->expects($this->any())->method('isOwner')->willReturn(false);
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->once())->method('getUID')->willReturn('user1');
		$this->reflector->reflect($controller, $methodName);

		$this->container->expects($this->any())
			->method('query')->willReturn($mapper);
		$this->userSession->expects($this->exactly(2))->method('getUser')->willReturn($user);
		$this->request->expects($this->once())->method('getMethod')->willReturn($method);
		if($getPermission) {
			$this->permissionService->expects($this->any())->method('getPermission')->willReturn($getPermission);
		}

		if($success) {
			$this->sharingMiddleware->beforeController($controller, $methodName);
		} else {
			try {
				$this->sharingMiddleware->beforeController($controller, $methodName);
			} catch (\Exception $e) {
				$this->assertInstanceOf($exception, $e);
			}
		}

	}

	public function setUpPermissions() {
		$this->permissionService->expects($this->once())
			->method('getPermission')
			->with(123, Acl::PERMISSION_READ)
			->willReturn(true);
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
		$result = $this->sharingMiddleware->afterException('Foo', 'bar', $exception);
		$expected = new JSONResponse([
			"status" => $status,
			"message" => $message
		], $status);
		$this->assertEquals($expected, $result);
	}

	public function testAfterExceptionFail() {
		try {
			$result = $this->sharingMiddleware->afterException('Foo', 'bar', new \Exception('failed hard'));
		} catch (\Exception $e) {
			$this->assertEquals('failed hard', $e->getMessage());
		}
	}

}