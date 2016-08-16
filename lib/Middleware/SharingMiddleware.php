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