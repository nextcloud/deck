<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Service\AttachmentService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

class AttachmentController extends Controller {
	public function __construct(
		$appName,
		IRequest $request,
		private AttachmentService $attachmentService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function getAll(int $cardId): array {
		return $this->attachmentService->findAll($cardId, true);
	}

	/**
	 * @throws \OCA\Deck\NotFoundException
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function display(int $cardId, string $attachmentId): Response {
		['type' => $type, 'attachmentId' => $attachmentId] = $this->extractTypeAndAttachmentId($attachmentId);
		return $this->attachmentService->display($cardId, $attachmentId, $type);
	}

	#[NoAdminRequired]
	public function create(int $cardId): Attachment {
		return $this->attachmentService->create(
			$cardId,
			$this->request->getParam('type'),
			$this->request->getParam('data') ?? '',
		);
	}

	#[NoAdminRequired]
	public function update(int $cardId, string $attachmentId): Attachment {
		['type' => $type, 'attachmentId' => $attachmentId] = $this->extractTypeAndAttachmentId($attachmentId);
		return $this->attachmentService->update($cardId, $attachmentId, $this->request->getParam('data') ?? '', $type);
	}

	#[NoAdminRequired]
	public function delete(int $cardId, string $attachmentId): Attachment {
		['type' => $type, 'attachmentId' => $attachmentId] = $this->extractTypeAndAttachmentId($attachmentId);
		return $this->attachmentService->delete($cardId, $attachmentId, $type);
	}

	#[NoAdminRequired]
	public function restore(int $cardId, string $attachmentId): Attachment {
		['type' => $type, 'attachmentId' => $attachmentId] = $this->extractTypeAndAttachmentId($attachmentId);
		return $this->attachmentService->restore($cardId, $attachmentId, $type);
	}

	/**
	 * @return array{type: string, attachmentId: int}
	 * @throws BadRequestException
	 */
	private function extractTypeAndAttachmentId(string $attachmentId): array {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = [...explode(':', $attachmentId), '', ''];
		}

		if ($type === '' || !is_numeric($attachmentId)) {
			throw new BadRequestException('Invalid attachment id');
		}

		return [
			'type' => $type,
			'attachmentId' => (int)$attachmentId,
		];
	}
}
