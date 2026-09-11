<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Stack;
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
		private ChangeHelper $changeHelper,
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
		$response = new DataResponse($stacks, HTTP::STATUS_OK);
		$response->setETag(md5(json_encode(array_map(function (Stack $stack) {
			$etag = $this->changeHelper->getEtag(ChangeHelper::TYPE_STACK, $stack->getId());
			if ($etag === '') {
				$etag = $stack->getETag();
			}
			return $stack->getId() . '-' . $etag;
		}, $stacks))));
		return $response;
	}

	/**
	 * Return all the stacks in the specified board.
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function get(): DataResponse {
		$stackId = (int)$this->request->getParam('stackId');
		$stack = $this->stackService->find($stackId);
		$response = new DataResponse($stack, HTTP::STATUS_OK);
		$etag = $this->changeHelper->getEtag(ChangeHelper::TYPE_STACK, $stackId);
		if ($etag === '') {
			$etag = $stack->getETag();
		}
		$response->setETag($etag);
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
		$stackId = (int)$this->request->getParam('stackId');
		$stack = $this->stackService->find($stackId);
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_STACK, $stackId, $stack->getETag());
		$stack = $this->stackService->update($stackId, $title, $this->request->getParam('boardId'), $order, 0);
		return new DataResponse($stack, HTTP::STATUS_OK);
	}

	/**
	 * Delete the stack specified by $this->request->getParam('stackId').
	 */
	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function delete(): DataResponse {
		$stackId = (int)$this->request->getParam('stackId');
		$stack = $this->stackService->find($stackId);
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_STACK, $stackId, $stack->getETag());
		$stack = $this->stackService->delete($stackId);
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
