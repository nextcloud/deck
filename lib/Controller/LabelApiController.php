<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\LabelService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
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
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get a specific label.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function get(): DataResponse {
		$label = $this->labelService->find($this->request->getParam('labelId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * Create a new label
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function create(string $title, string $color): DataResponse {
		$label = $this->labelService->create($title, $color, $this->request->getParam('boardId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * Update a specific label
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function update(string $title, string $color): DataResponse {
		$label = $this->labelService->update($this->request->getParam('labelId'), $title, $color);
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * Delete a specific label
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function delete(): DataResponse {
		$label = $this->labelService->delete($this->request->getParam('labelId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}
}
