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
use OCA\Deck\Controller\CardController;
use OCA\Deck\Controller\LabelController;
use OCA\Deck\Controller\PageController;


use OCA\Deck\Db\AclMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;

use OCA\Deck\Service\PermissionService;
use OCA\Deck\StatusException;
use \OCP\AppFramework\Middleware;
use OCP\IContainer;
use OCP\IGroupManager;
use OCP\IRequest;
use OCA\Deck\Controller\StackController;
use OCP\IUserSession;
use OCP\AppFramework\Http\JSONResponse;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCA\Deck\Db\Acl;

class SharingMiddleware extends Middleware {

	private $container;
	private $request;
	private $userSession;
	private $reflector;
	private $permissionService;


	public function __construct(
		IContainer $container,
		IRequest $request,
		IUserSession $userSession,
		ControllerMethodReflector $reflector,
		PermissionService $permissionService
	) {
		$this->container = $container;
		$this->request = $request;
		$this->userSession = $userSession;
		$this->reflector = $reflector;
		$this->permissionService = $permissionService;
	}

	/**
	 * Return JSON error response if the user has no sufficient permission
	 *
	 * @param \OCP\AppFramework\Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @return JSONResponse
	 * @throws \Exception
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($exception instanceof StatusException) {
			return new JSONResponse([
				"status" => $exception->getStatus(),
				"message" => $exception->getMessage()
			], $exception->getStatus());
		}
		throw $exception;
	}

}