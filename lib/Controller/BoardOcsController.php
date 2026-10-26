<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\ExternalBoardService;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\Teams\ITeamManager;
use Psr\Log\LoggerInterface;

class BoardOcsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private BoardService $boardService,
		private BoardMapper $boardMapper,
		private ExternalBoardService $externalBoardService,
		private ITeamManager $teamManager,
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
	public function listForTeam(string $teamId): DataResponse {
		if ($this->userId === null || !$this->teamManager->isMemberOfTeam($teamId, $this->userId)) {
			throw new OCSNotFoundException('Team not found');
		}
		$boards = array_map(static fn ($board) => [
			'id' => $board->getId(),
			'title' => $board->getTitle(),
			'teamId' => $board->getTeamId(),
			'archived' => $board->getArchived(),
			'deletedAt' => $board->getDeletedAt(),
		], $this->boardMapper->findAllAttachedToTeam($teamId));
		return new DataResponse($boards);
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

	/**
	 * Create a board attached to a team (circle).
	 */
	#[NoAdminRequired]
	public function createForTeam(string $title, string $teamId, ?string $color = null): DataResponse {
		return new DataResponse($this->boardService->createForTeam($title, $this->userId, $color, $teamId));
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
