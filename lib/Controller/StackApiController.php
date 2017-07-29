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

use OCA\Deck\Service\StackService;

/**
 * Class StackApiController
 *
 * @package OCA\Deck\Controller
 */
class StackApiController extends ApiController {

    private $service;
    private $userInfo;

    /**
     * @param string $appName
     * @param IRequest $request
     * @param StackService $service
     * @param $boardId
     */
    public function __construct($appName, IRequest $request, StackService $service, $boardId) {
        parent::__construct($appName, $request);
        $this->service = $service;
        $this->boardId = $boardId;
    }

    /**
     * @NoAdminRequired
     * @CORS
     * @NoCSRFRequired
     *
     * Return all of the stacks in the specified board.
     */
    public function index() {
        $stacks = $this->service->findAll($this->boardId);

        return new DataResponse($stacks);
    }

    /**
     * @NoAdminRequired
     * @CORS
     * @NoCSRFRequired
     *
     * @params $title
     * @params $order
     *
     * Create a stack with the specified title and order.
     */
    public function create($title, $order) {
        // this throws a StatusException that needs to be caught and handled
        $stack = $this->service->create($title, $this->boardId, $order);

        return new DataResponse($stack);
    }

    /**
     * @NoAdminRequired
     * @CORS
     * @NoCSRFRequired
     *
     * @params $id
     *
     * Delete the stack specified by $id.  Return the board that was deleted.
     */
    public function delete($id) {
        $stack = $this->service->delete($id);

        return new DataResponse($stack);
    }

}
