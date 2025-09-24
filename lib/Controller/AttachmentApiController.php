<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Controller;

use OCA\Deck\Db\Attachment;
use OCA\Deck\Service\AttachmentService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class AttachmentApiController extends ApiController {
	public function __construct(
		$appName,
		IRequest $request,
		private AttachmentService $attachmentService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function getAll(string $apiVersion): DataResponse {
		$attachment = $this->attachmentService->findAll($this->request->getParam('cardId'), true);
		if ($apiVersion === '1.0') {
			$attachment = array_filter($attachment, fn (Attachment $attachment): bool => $attachment->getType() === 'deck_file');
		}
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function display(int $cardId, int $attachmentId, string $type = 'deck_file') {
		return $this->attachmentService->display($cardId, $attachmentId, $type);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function create(int $cardId, string $type, string $data): DataResponse {
		$attachment = $this->attachmentService->create($cardId, $type, $data);
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function update(int $cardId, int $attachmentId, string $data, string $type = 'deck_file'): DataResponse {
		$attachment = $this->attachmentService->update($cardId, $attachmentId, $data, $type);
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function delete(int $cardId, int $attachmentId, string $type = 'deck_file'): DataResponse {
		$attachment = $this->attachmentService->delete($cardId, $attachmentId, $type);
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}

	#[NoAdminRequired]
	#[CORS]
	#[NoCSRFRequired]
	public function restore(int $cardId, int $attachmentId, string $type = 'deck_file'): DataResponse {
		$attachment = $this->attachmentService->restore($cardId, $attachmentId, $type);
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}
}
