<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\LabelService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class LabelApiController extends ApiController {
	/**
	 * @param string $appName
	 */
	public function __construct(
		$appName,
		IRequest $request,
		private LabelService $labelService,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Get a specific label.
	 */
	public function get() {
		$label = $this->labelService->find($this->request->getParam('labelId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $color
	 * Create a new label
	 */
	public function create($title, $color) {
		$label = $this->labelService->create($title, $color, $this->request->getParam('boardId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $color
	 * Update a specific label
	 */
	public function update($title, $color) {
		$label = $this->labelService->update($this->request->getParam('labelId'), $title, $color);
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Delete a specific label
	 */
	public function delete() {
		$label = $this->labelService->delete($this->request->getParam('labelId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}
}
