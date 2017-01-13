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

	public function setUp() {
		$this->sharingMiddleware = new SharingMiddleware();
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