<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\ExternalBoardService;
use OCA\Deck\Service\StackService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
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
		private StackService $stackService,
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
	#[NoCSRFRequired]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function read(int $boardId): DataResponse {
		// Board on this instance -> get it from database
		$localBoard = $this->boardService->find($boardId, true, true);
		if ($localBoard->getExternalId() !== null) {
			return $this->externalBoardService->getExternalBoardFromRemote($localBoard);
		}
		// Board on other instance -> get it from other instance
		return new DataResponse($localBoard);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function create(string $title, string $color): DataResponse {
		return new DataResponse($this->boardService->create($title, $this->userId, $color));
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function stacks(int $boardId): DataResponse {
		$localBoard = $this->boardService->find($boardId, true, true);
		// Board on other instance -> get it from other instance
		if ($localBoard->getExternalId() !== null) {
			return $this->externalBoardService->getExternalStacksFromRemote($localBoard);
		} else {
			return new DataResponse($this->stackService->findAll($boardId));
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function addAcl(int $boardId, int $type, string $participant, bool $permissionEdit, bool $permissionShare, bool $permissionManage, ?string $remote = null): DataResponse {
		return new DataResponse($this->boardService->addAcl($boardId, $type, $participant, $permissionEdit, $permissionShare, $permissionManage));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function updateAcl(int $id, bool $permissionEdit, bool $permissionShare, bool $permissionManage): DataResponse {
		return new DataResponse($this->boardService->updateAcl($id, $permissionEdit, $permissionShare, $permissionManage));
	}
}
