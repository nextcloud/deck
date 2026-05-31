<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\ExternalBoardService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class BoardOcsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private BoardService $boardService,
		private ExternalBoardService $externalBoardService,
		private LoggerInterface $logger,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$internalBoards = $this->boardService->findAll();
		return new DataResponse($internalBoards);
	}

	#[NoAdminRequired]
	#[PublicPage]
	public function read(int $boardId): DataResponse {
		$localBoard = $this->boardService->find($boardId, true, true);
		if ($localBoard->getExternalId() !== null) {
			return $this->externalBoardService->getExternalBoardFromRemote($localBoard);
		}
		return new DataResponse($localBoard);
	}

	#[NoAdminRequired]
	public function create(string $title, string $color): DataResponse {
		return new DataResponse($this->boardService->create($title, $this->userId, $color));
	}

	#[NoAdminRequired]
	public function addAcl(int $boardId, int $type, string $participant, bool $permissionEdit, bool $permissionShare, bool $permissionManage, ?string $remote = null): DataResponse {
		return new DataResponse($this->boardService->addAcl($boardId, $type, $participant, $permissionEdit, $permissionShare, $permissionManage));
	}

	#[NoAdminRequired]
	public function updateAcl(int $id, bool $permissionEdit, bool $permissionShare, bool $permissionManage): DataResponse {
		return new DataResponse($this->boardService->updateAcl($id, $permissionEdit, $permissionShare, $permissionManage));
	}
}
