<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\StackService;
use OCA\Deck\StatusException;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use function Sabre\HTTP\parseDate;

/**
 * Class StackApiController
 *
 * @package OCA\Deck\Controller
 */
class StackApiController extends ApiController {
	/**
	 * @param string $appName
	 */
	public function __construct(
		$appName,
		IRequest $request,
		private StackService $stackService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Return all the stacks in the specified board.
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function index(): DataResponse {
		$since = 0;
		$modified = $this->request->getHeader('If-Modified-Since');
		if ($modified !== '') {
			$date = parseDate($modified);
			if (!$date) {
				throw new StatusException('Invalid If-Modified-Since header provided.');
			}
			$since = $date->getTimestamp();
		}
		$stacks = $this->stackService->findAll($this->request->getParam('boardId'), $since);
		return new DataResponse($stacks, HTTP::STATUS_OK);
	}

	/**
	 * Return all the stacks in the specified board.
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function get(): DataResponse {
		$stack = $this->stackService->find($this->request->getParam('stackId'));
		$response = new DataResponse($stack, HTTP::STATUS_OK);
		$response->setETag($stack->getETag());
		return $response;
	}

	/**
	 * Create a stack with the specified title and order.
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function create(string $title, int $order): DataResponse {
		$stack = $this->stackService->create($title, $this->request->getParam('boardId'), $order);
		return new DataResponse($stack, HTTP::STATUS_OK);
	}

	/**
	 * Update a stack by the specified stackId and boardId with the values that were put.
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function update(string $title, int $order) {
		$stack = $this->stackService->update($this->request->getParam('stackId'), $title, $this->request->getParam('boardId'), $order, 0);
		return new DataResponse($stack, HTTP::STATUS_OK);
	}

	/**
	 * Delete the stack specified by $this->request->getParam('stackId').
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function delete(): DataResponse {
		$stack = $this->stackService->delete($this->request->getParam('stackId'));
		return new DataResponse($stack, HTTP::STATUS_OK);
	}

	/**
	 * Get the stacks that have been archived.
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function getArchived(): DataResponse {
		$stacks = $this->stackService->findAllArchived($this->request->getParam('boardId'));
		return new DataResponse($stacks, HTTP::STATUS_OK);
	}
}
