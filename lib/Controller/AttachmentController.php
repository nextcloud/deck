<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\AttachmentService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class AttachmentController extends Controller {
	public function __construct(
		$appName,
		IRequest $request,
		private AttachmentService $attachmentService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getAll($cardId) {
		return $this->attachmentService->findAll($cardId, true);
	}

	/**
	 * @param $cardId
	 * @param $attachmentId
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @return \OCP\AppFramework\Http\Response
	 * @throws \OCA\Deck\NotFoundException
	 */
	public function display($cardId, $attachmentId) {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->display($cardId, $attachmentId, $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function create($cardId) {
		return $this->attachmentService->create(
			$cardId,
			$this->request->getParam('type'),
			$this->request->getParam('data')
		);
	}

	/**
	 * @NoAdminRequired
	 */
	public function update($cardId, $attachmentId) {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->update($cardId, $attachmentId, $this->request->getParam('data'), $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function delete($cardId, $attachmentId) {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->delete($cardId, $attachmentId, $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function restore($cardId, $attachmentId) {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->restore($cardId, $attachmentId, $type);
	}
}
