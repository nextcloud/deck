<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\NotImplementedException;
use OCA\Deck\Service\AttachmentService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\ExternalBoardService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class AttachmentOcsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private AttachmentService $attachmentService,
		private BoardService $boardService,
		private ExternalBoardService $externalBoardService,
	) {
		parent::__construct($appName, $request);
	}

	private function ensureLocalBoard(?int $boardId): void {
		if ($boardId) {
			$board = $this->boardService->find($boardId);
			if ($board->getExternalId()) {
				throw new NotImplementedException('attachments for federated boards are not supported');
			}
		}
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function getAll(int $cardId, ?int $boardId = null): DataResponse {
		$board = $this->boardService->find($boardId, false);
		if ($board->getExternalId()) {
			return new DataResponse($this->externalBoardService->getAttachmentsFromRemote($board, $cardId));
		}
		$attachment = $this->attachmentService->findAll($cardId, true);
		return new DataResponse($attachment);
	}

	#[NoAdminRequired]
	public function create(int $cardId, string $type, string $data = '', ?int $boardId = null): DataResponse {
		$board = $this->boardService->find($boardId, false);
		if ($board->getExternalId()) {
			return new DataResponse($this->externalBoardService->createAttachmentForRemote($board, $cardId));
		}
		$attachment = $this->attachmentService->create($cardId, $type, $data);
		return new DataResponse($attachment);
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function acceptRemote(int $cardId, string $token): DataResponse {
		$this->attachmentService->acceptRemoteAttachment($cardId, $token);
		return new DataResponse([]);
	}

	#[NoAdminRequired]
	public function update(int $cardId, int $attachmentId, string $data, string $type = 'file', ?int $boardId = null): DataResponse {
		$this->ensureLocalBoard($boardId);
		$attachment = $this->attachmentService->update($cardId, $attachmentId, $data, $type);
		return new DataResponse($attachment);
	}

	#[NoAdminRequired]
	public function delete(int $cardId, int $attachmentId, string $type = 'file', ?int $boardId = null): DataResponse {
		$this->ensureLocalBoard($boardId);
		$attachment = $this->attachmentService->delete($cardId, $attachmentId, $type);
		return new DataResponse($attachment);
	}

	#[NoAdminRequired]
	public function restore(int $cardId, int $attachmentId, string $type = 'file', ?int $boardId = null): DataResponse {
		$this->ensureLocalBoard($boardId);
		$attachment = $this->attachmentService->restore($cardId, $attachmentId, $type);
		return new DataResponse($attachment);
	}

}
