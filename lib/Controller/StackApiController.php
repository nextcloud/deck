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

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

use OCA\Deck\StatusException;
use OCA\Deck\Service\StackService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Controller\Helper\ApiHelper;

/**
 * Class StackApiController
 *
 * @package OCA\Deck\Controller
 */
class StackApiController extends ApiController {

	private $boardService;
	private $stackService;
	private $userInfo;
	private $apiHelper;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param StackService $stackService
	 */
	public function __construct($appName, IRequest $request, StackService $stackService, BoardService $boardService) {
		parent::__construct($appName, $request);
		$this->service = $stackService;
		$this->boardService = $boardService;
		$this->apiHelper = new ApiHelper();
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Return all of the stacks in the specified board.
	 */
	public function index() {		
		$boardError = $this->apiHelper->entityHasError($this->request->params['boardId'], 'board', $this->boardService);

		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}
		
		$stacks = $this->service->findAll($this->request->params['boardId']);

		if ($stacks === false || $stacks === null) {
			return new DataResponse('No Stacks Found', HTTP::STATUS_NOT_FOUND);
		}

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
		$boardError = $this->apiHelper->entityHasError($this->request->params['boardId'], 'board', $this->boardService);

		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}
		
		$stack = $this->service->find($this->request->params['stackId']);

		if ($stack == false || $stack == null) {
			return new DataResponse("Stack not found", HTTP::STATUS_NOT_FOUND);
		}

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
	 * Create a stack with the specified title and order.
	 */
	public function create($title, $order) {		

		$boardError = $this->apiHelper->entityHasError( $this->request->params['boardId'], 'board', $this->boardService );

		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}

		if (is_numeric($order) === false) {
			return new DataResponse('order must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		try {
			$stack = $this->service->create($title, $this->request->params['boardId'], $order);
		} catch (StatusException $e) {
			$errorMessage['error'] = $e->getMessage();
			return new DataResponse($errorMessage, HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}
		
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
		
		$boardError = $this->apiHelper->entityHasError( $this->request->params['boardId'], 'board', $this->boardService );

		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}
		
		if (is_numeric($this->request->params['stackId']) === false) {
			return new DataResponse('stack id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		if (is_numeric($order) === false) {
			return new DataResponse('order must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		try {
			$stack = $this->service->update($this->request->params['stackId'], $title, $this->request->params['boardId'], $order);

			if ($stack === false || $stack === null) {
				return new DataResponse('Stack not found', HTTP::STATUS_NOT_FOUND);
			}
	
			return new DataResponse($stack, HTTP::STATUS_OK);
		} catch (StatusException $e) {			
			return new DataResponse($e->getMessage(), HTTP::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Delete the stack specified by $this->request->params['stackId'].
	 */
	public function delete() {

		$boardError = $this->apiHelper->entityHasError( $this->request->params['boardId'], 'board', $this->boardService );

		if ($boardError) {
			return new DataResponse($boardError['message'], $boardError['status']);
		}

		if (is_numeric($this->request->params['stackId']) === false) {
			return new DataResponse('stack id must be a number', HTTP::STATUS_BAD_REQUEST);
		}

		$stack = $this->service->delete($this->request->params['stackId']);		
		
		if ($stack == false || $stack == null) {
			return new DataResponse('Stack Not Found', HTTP::STATUS_NOT_FOUND);
		}

		return new DataResponse($stack, HTTP::STATUS_OK);
	}
}
