<?php

namespace OCA\Deck\Middleware;

use \OCP\AppFramework\Middleware;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;


class SharingMiddleware extends Middleware {

	public function __construct(
		IUserManager $userManager,
		IGroupManager $groupManager,
		IDBConnection $db,
		$userId
	) {
		$this->userId = $userId;
		$this->db = $db;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	public function beforeController($controller, $methodName) {
		\OCP\Util::writeLog('deck', "", \OCP\Util::ERROR);
		//$userBoards = $this->boardMapper->findAllByUser($userInfo['user']);
		//$groupBoards = $this->boardMapper->findAllByGroups($userInfo['user'], $userInfo['groups']);

	}

}