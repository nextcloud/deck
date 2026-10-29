<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\ChangeHelper;
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
		private ChangeHelper $changeHelper,
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
		$labelId = (int)$this->request->getParam('labelId');
		$label = $this->labelService->find($labelId);
		$response = new DataResponse($label, HTTP::STATUS_OK);
		$etag = $this->changeHelper->getEtag(ChangeHelper::TYPE_LABEL, $labelId);
		if ($etag === '') {
			$etag = $label->getETag();
		}
		$response->setETag($etag);
		return $response;
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
		$labelId = (int)$this->request->getParam('labelId');
		$label = $this->labelService->find($labelId);
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_LABEL, $labelId, $label->getETag());
		$label = $this->labelService->update($labelId, $title, $color);
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * Delete a specific label
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function delete(): DataResponse {
		$labelId = (int)$this->request->getParam('labelId');
		$label = $this->labelService->find($labelId);
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_LABEL, $labelId, $label->getETag());
		$label = $this->labelService->delete($labelId);
		return new DataResponse($label, HTTP::STATUS_OK);
	}
}
