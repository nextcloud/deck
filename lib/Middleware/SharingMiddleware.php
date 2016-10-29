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

use OCA\Deck\Service\BoardService;
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
	private $groupManager;
	private $aclMapper;
	private $boardService;


	public function __construct(
		IContainer $container,
		IRequest $request,
		IUserSession $userSession,
		ControllerMethodReflector $reflector,
		IGroupManager $groupManager,
		AclMapper $aclMapper,
		BoardService $boardService
	) {
		$this->container = $container;
		$this->request = $request;
		$this->userSession = $userSession;
		$this->reflector = $reflector;
		$this->aclMapper = $aclMapper;
		$this->groupManager = $groupManager;
		$this->boardService = $boardService;
	}

	/**
	 * All permission checks for controller access
	 *
	 * The following method annotations are possible
	 * - RequireReadPermission
	 * - RequireEditPermission
	 * - RequireSharePermission
	 * - RequireManagePermission
	 * - RequireNoPermission
	 *
	 * Depending on the Controller class we call a corresponding mapper to find the board_id
	 * With the board_id we can check for ownership/permissions in the acl table
	 *
	 * @param \OCP\AppFramework\Controller $controller
	 * @param string $methodName
	 * @throws NoPermissionException
	 */
	public function beforeController($controller, $methodName) {

		$userId = null;
		if ($this->userSession->getUser()) {
			$userId = $this->userSession->getUser()->getUID();
		}
		$method = $this->request->getMethod();
		$params = $this->request->getParams();
		$this->checkPermissions($userId, $controller, $method, $params, $methodName);

	}

	/**
	 * Check permission depending on the route (controller/method)
	 *
	 * @param $userId
	 * @param $controller
	 * @param $method
	 * @param $params
	 * @param $methodName
	 * @return bool
	 * @throws NoPermissionException
	 * @throws \Exception
	 */
	private function checkPermissions($userId, $controller, $method, $params, $methodName) {

		// no permission checks needed for plain html page or RequireNoPermission
		if (
			$controller instanceof PageController ||
			$this->reflector->hasAnnotation('RequireNoPermission')
		) {
			return true;
		}

		$mapper = null;
		$id = null;

		if ($controller instanceof BoardController) {
			$mapper = $this->container->query('OCA\Deck\Db\BoardMapper');
			$id = $params['boardId'];
		}

		if ($controller instanceof StackController) {
			if ($method === "GET" || $method === "POST") {
				$mapper = $this->container->query('OCA\Deck\Db\BoardMapper');
				$id = $params['boardId'];
			} else {
				$mapper = $this->container->query('OCA\Deck\Db\StackMapper');
				$id = $params['stackId'];
			}

		}
		if ($controller instanceof CardController) {
			if ($method === "POST" && !preg_match('/Label/', $methodName)) {
				$mapper = $this->container->query('OCA\Deck\Db\StackMapper');
				$id = $params['stackId'];
			} else {
				$mapper = $this->container->query('OCA\Deck\Db\CardMapper');
				$id = $params['cardId'];
			}

		}
		if ($controller instanceof LabelController) {
			if ($method === "GET" || $method === "POST") {
				$mapper = $this->container->query('OCA\Deck\Db\BoardMapper');
				$id = $params['boardId'];
			} else {
				$mapper = $this->container->query('OCA\Deck\Db\LabelMapper');
				$id = $params['labelId'];
			}
		}

		// check if there is a mapper so we can find the corresponding board for the request
		if ($mapper === null) {
			throw new \Exception("No mappers specified for permission checks");
		}

		if ($this->reflector->hasAnnotation('RequireReadPermission')) {
			if (!$this->checkMapperPermission(Acl::PERMISSION_READ, $userId, $mapper, $id)) {
				throw new NoPermissionException("User " . $userId . " has no permission to read.", $controller, $methodName);
			}
		}
		if ($this->reflector->hasAnnotation('RequireEditPermission')) {
			if (!$this->checkMapperPermission(Acl::PERMISSION_EDIT, $userId, $mapper, $id)) {
				throw new NoPermissionException("User " . $userId . " has no permission to edit.", $controller, $methodName);
			}
		}
		if ($this->reflector->hasAnnotation('RequireSharePermission')) {
			if (!$this->checkMapperPermission(Acl::PERMISSION_SHARE, $userId, $mapper, $id)) {
				throw new NoPermissionException("User " . $userId . " has no permission to share.", $controller, $methodName);
			}
		}
		if ($this->reflector->hasAnnotation('RequireManagePermission')) {
			if (!$this->checkMapperPermission(Acl::PERMISSION_MANAGE, $userId, $mapper, $id)) {
				throw new NoPermissionException("User " . $userId . " has no permission to manage.", $controller, $methodName);
			}
		}
		// all permission checks succeeded
		return true;

	}

	/**
	 * Check if $userId is authorized for $permission on board related to $mapper with $id
	 *
	 * @param $permission
	 * @param $userId
	 * @param $mapper
	 * @param $id
	 * @return bool
	 * @throws NotFoundException
	 */
	public function checkMapperPermission($permission, $userId, $mapper, $id) {
		// check if current user is owner
		if ($mapper->isOwner($userId, $id)) {
			return true;
		}
		// find related board
		$boardId = $mapper->findBoardId($id);
		if(!$boardId) {
			throw new NotFoundException("Entity not found");
		}
		return $this->boardService->getPermission($boardId, $userId, $permission);
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
		if (is_a($exception, '\OCA\Deck\NoPermissionException')) {
			return new JSONResponse([
				"status" => 401,
				"message" => $exception->getMessage()
			], 401);
		}
		if (is_a($exception, '\OCA\Deck\NotFoundException')) {
			return new JSONResponse([
				"status" => 404,
				"message" => $exception->getMessage()
			], 404);
		}
		throw $exception;
	}


}