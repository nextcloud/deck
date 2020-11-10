<?php
/**
 * @copyright Copyright (c) 2017 Steven R. Baker <steven@stevenrbaker.com>
 *
 * @author Steven R. Baker <steven@stevenrbaker.com>
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
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

use OCA\Deck\StatusException;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCA\Deck\Service\StackService;
use OCA\Deck\Service\BoardService;
use Sabre\HTTP\Util;

/**
 * Class StackApiController
 *
 * @package OCA\Deck\Controller
 */
class StackApiController extends ApiController {
	private $boardService;
	private $stackService;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param StackService $stackService
	 */
	public function __construct($appName, IRequest $request, StackService $stackService, BoardService $boardService) {
		parent::__construct($appName, $request);
		$this->stackService = $stackService;
		$this->boardService = $boardService;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Return all of the stacks in the specified board.
	 */
	public function index() {
		$since = 0;
		$modified = $this->request->getHeader('If-Modified-Since');
		if ($modified !== null && $modified !== '') {
			$date = Util::parseHTTPDate($modified);
			if (!$date) {
				throw new StatusException('Invalid If-Modified-Since header provided.');
			}
			$since = $date->getTimestamp();
		}
		$stacks = $this->stackService->findAll($this->request->getParam('boardId'), $since);
		return new DataResponse($stacks, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Return all of the stacks in the specified board.
	 */
	public function get() {
		$stack = $this->stackService->find($this->request->getParam('stackId'));
		$response = new DataResponse($stack, HTTP::STATUS_OK);
		$response->setETag($stack->getETag());
		return $response;
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
		$stack = $this->stackService->create($title, $this->request->getParam('boardId'), $order);
		return new DataResponse($stack, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $order
	 *
	 * Update a stack by the specified stackId and boardId with the values that were put.
	 */
	public function update($title, $order) {
		$stack = $this->stackService->update($this->request->getParam('stackId'), $title, $this->request->getParam('boardId'), $order, 0);
		return new DataResponse($stack, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Delete the stack specified by $this->request->getParam('stackId').
	 */
	public function delete() {
		$stack = $this->stackService->delete($this->request->getParam('stackId'));
		return new DataResponse($stack, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * get the stacks that have been archived.
	 */
	public function getArchived() {
		$stacks = $this->stackService->findAllArchived($this->request->getParam('boardId'));
		return new DataResponse($stacks, HTTP::STATUS_OK);
	}
}
