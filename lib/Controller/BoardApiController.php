<?php
/**
 * @copyright Copyright (c) 2017 Steven R. Baker <steven@stevenrbaker.com>
 *
 * @author Steven R. Baker <steven@stevenrbaker.com>
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

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;

use OCA\Deck\Service\BoardService;

/**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class BoardApiController extends ApiController {

    private $service;
    private $userInfo;

    /**
     * @param string $appName
     * @param IRequest $request
     * @param BoardService $service
     */
    public function __construct($appName, IRequest $request, IUserManager $userManager, IGroupManager $groupManager, BoardService $service, $userId) {
        parent::__construct($appName, $request);
        $this->service = $service;
        $this->userId = $userId;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
    }

    /**
     * @NoAdminRequired
     * @CORS
     * @NoCSRFRequired
     */
    public function index() {
        $boards = $this->service->findAll($this->getUserInfo());

        return (new DataResponse($boards));
    }

    /**
     * @NoAdminRequired
     * @CORS
     * @NoCSRFRequired
     */
    public function get($id) {
        $board = $this->service->find($id);

        return (new DataResponse($board));
    }

    // this is taken from BoardController, but it's not ideal
    private function getUserInfo() {
        $groups = $this->groupManager->getUserGroupIds(
			$this->userManager->get($this->userId)
		);
        return ['user' => $this->userId,
                'groups' => $groups];
    }

}
