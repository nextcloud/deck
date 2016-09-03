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

use OC\OCS\Exception;
use OCA\Deck\Controller\BoardController;
use OCA\Deck\Controller\CardController;
use OCA\Deck\Controller\LabelController;
use OCA\Deck\Controller\PageController;
use OCA\Deck\Controller\ShareController;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\ServicePermissionException;
use OCA\Files_Versions\Expiration;
use \OCP\AppFramework\Middleware;
use OCP\IContainer;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCA\Deck\Controller\StackController;
use OCP\IUserSession;
use OCP\AppFramework\Http\JSONResponse;
use OC\AppFramework\Utility\ControllerMethodReflector;


class SharingMiddleware extends Middleware {

	private $container;
	private $request;
	private $userSession;
	private $reflector;
	private $groupManager;

	public function __construct(
		IContainer $container,
		IRequest $request,
		IUserSession $userSession,
		ControllerMethodReflector $reflector
	) {
		$this->container = $container;
		$this->request = $request;
		$this->userSession = $userSession;
		$this->reflector = $reflector;
		$this->aclMapper = $this->container->query('OCA\Deck\Db\AclMapper');
		$this->groupManager = $this->container->query('\OCP\IGroupManager');

	}

	/**
	 * All permission checks for controller access
	 *
	 * The following method annotaitons are possible
	 * - RequireReadPermission
	 * - RequireEditPermission
	 * - RequireSharePermission
	 * - RequireManagePermission
	 *
	 * Depending on the Controller class we call a corresponding mapper to find the board_id
	 * With the board_id we can check for ownership/permissions in the acl table
	 *
	 * @param \OCP\AppFramework\Controller $controller
	 * @param string $methodName
	 * @throws NoPermissionException
	 */
	public function beforeController($controller, $methodName) {
		$this->start = microtime(true);
		$userId = null;
		if($this->userSession->getUser()) {
			$userId = $this->userSession->getUser()->getUID();
		}
		$method = $this->request->getMethod();
		$params = $this->request->getParams();
		$this->checkPermissions($userId, $controller, $method, $params);

		// TODO: remove, just for testing
		\OCP\Util::writeLog('deck', (microtime(true)-$this->start), \OCP\Util::ERROR);

	}

	private function checkPermissions($userId, $controller, $method, $params) {

		// no permission checks needed for plain html page
		if($controller instanceof PageController) {
			return;
		}

		// FIXME: ShareController#search should be limited to board users/groups
		if($controller instanceof BoardController or $controller instanceof ShareController) {
			$mapper = $this->container->query('OCA\Deck\Db\BoardMapper');
			$id = $params['boardId'];
		}

		if($controller instanceof StackController) {
			if($method==="GET" || $method === "POST") {
				$mapper = $this->container->query('OCA\Deck\Db\BoardMapper');
				$id = $params['boardId'];
			} else {
				$mapper = $this->container->query('OCA\Deck\Db\StackMapper');
				$id = $params['stackId'];
			}

		}
		if($controller instanceof CardController) {
			if($method === "POST" && !preg_match('/Label/', $method)) {
				$mapper = $this->container->query('OCA\Deck\Db\StackMapper');
				$id = $params['stackId'];
			} else {
				$mapper = $this->container->query('OCA\Deck\Db\CardMapper');
				$id = $params['cardId'];
			}

		}
		if($controller instanceof LabelController) {
			if($method==="GET" || $method === "POST") {
				$mapper = $this->container->query('OCA\Deck\Db\BoardMapper');
				$id = $params['boardId'];
			} else {
				$mapper = $this->container->query('OCA\Deck\Db\LabelMapper');
				$id = $params['labelId'];
			}

		}


		if($this->reflector->hasAnnotation('RequireReadPermission')) {
			if(!$this->checkReadPermission($userId, $mapper, $id)) {
				throw new NoPermissionException("User ". $userId . " has no permission to read.", $controller, $method);
			}
		}
		if($this->reflector->hasAnnotation('RequireEditPermission')) {
			if(!$this->checkEditPermission($userId, $mapper, $id)) {
				throw new NoPermissionException("User ". $userId . " has no permission to edit.", $controller, $method);
			}

		}
		if($this->reflector->hasAnnotation('RequireSharePermission')) {
			if(!$this->checkSharePermission($userId, $mapper, $id)) {
				throw new NoPermissionException("User ". $userId . " has no permission to share.", $controller, $method);
			}
		}
		if($this->reflector->hasAnnotation('RequireManagePermission')) {
			if(!$this->checkManagePermission($userId, $mapper, $id)) {
				throw new NoPermissionException("User ". $userId . " has no permission to manage.", $controller, $method);
			}
		}

		// FIXME: Default should be nopermisison mybe add norequirepermission for index

	}

	/* TODO: Priorize groups with higher permissions */
	public function checkReadPermission($userId, $mapper, $id) {
		// is owner
		if($mapper->isOwner($userId, $id)) {
			return true;
		}
		$boardId = $mapper->findBoardId($id);
		$acls = $this->aclMapper->findAllShared($boardId);
		// check for users
		foreach ($acls as $acl) {
			if($acl->getType() === "user" && $acl->getParticipant() === $userId) {
				return true;
			}
		}
		// check for groups
		foreach ($acls as $acl) {
			if($acl->getType() === "user" && $this->groupManager->isInGroup($userId, $acl->getParticipant())) {
				return true;
			}
		}

		throw new NoPermissionException("User ". $userId . " has no permission to read.");

	}
	public function checkEditPermission($userId, $mapper, $id) {
		// is owner
		if($mapper->isOwner($userId, $id)) {
			return true;
		}
		// check if is in acl
		$boardId = $mapper->findBoardId($id);
		$acls = $this->aclMapper->findAllShared($boardId);
		// check for users
		foreach ($acls as $acl) {
			if($acl->getType() === "user" && $acl->getParticipant() === $userId) {
				return $acl->getPermissionWrite();
			}
		}
		// check for groups
		foreach ($acls as $acl) {
			if($acl->getType() === "user" && $this->groupManager->isInGroup($userId, $acl->getParticipant())) {
				return $acl->getPermissionWrite();
			}
		}

		throw new NoPermissionException("User ". $userId . " has no permission to edit.");

	}
	public function checkManagePermission($userId, $mapper, $id) {
		// is owner
		if($mapper->isOwner($userId, $id)) {
			return true;
		}
		// check if is in acl
		$boardId = $mapper->findBoardId($id);
		$acls = $this->aclMapper->findAllShared($boardId);
		// check for users
		foreach ($acls as $acl) {
			if($acl->getType() === "user" && $acl->getParticipant() === $userId) {
				return $acl->getPermissionManage();
			}
		}
		// check for groups
		foreach ($acls as $acl) {
			if($acl->getType() === "user" && $this->groupManager->isInGroup($userId, $acl->getParticipant())) {
				return $acl->getPermissionManage();
			}
		}

		throw new NoPermissionException();

	}
	public function checkSharePermission($userId, $mapper, $id) {
		// is owner
		if($mapper->isOwner($userId, $id)) {
			return true;
		}
		// check if is in acl
		$boardId = $mapper->findBoardId($id);
		$acls = $this->aclMapper->findAllShared($boardId);
		// check for users
		foreach ($acls as $acl) {
			if($acl->getType() === "user" && $acl->getParticipant() === $userId) {
				return $acl->getPermissionInvite();
			}
		}
		// check for groups
		foreach ($acls as $acl) {
			if($acl->getType() === "user" && $this->groupManager->isInGroup($userId, $acl->getParticipant())) {
				return $acl->getPermissionInvite();
			}
		}

		throw new NoPermissionException();

	}

	public function afterException($controller, $methodName, \Exception $exception) {
		\OCP\Util::writeLog('deck', (microtime(true)-$this->start), \OCP\Util::ERROR);
		if(is_a($exception, '\OCA\Deck\NoPermissionException')) {
			return new JSONResponse([
				"status" => 401,
				"message" => $exception->getMessage()
			], 401);
		}
		throw $exception;
	}


}