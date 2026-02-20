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

class StackOcsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ExternalBoardService $externalBoardService,
		private BoardService $boardService,
		private StackService $stackService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function create(string $title, int $boardId, int $order = 0):DataResponse {
		$board = $this->boardService->find($boardId, false);
		if ($board->getExternalId()) {
			$stack = $this->externalBoardService->createStackOnRemote($board, $title, $order);
			return new DataResponse($stack);
		} else {
			$stack = $this->stackService->create($title, $boardId, $order);
			return new DataResponse($stack);
		};
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function delete(int $stackId, ?int $boardId = null):DataResponse {
		if ($boardId) {
			$board = $this->boardService->find($boardId, false);
			if ($board->getExternalId()) {
				$result = $this->externalBoardService->deleteStackOnRemote($board, $stackId);
				return new DataResponse($result);
			}
		}
		$result = $this->stackService->delete($stackId);
		return new DataResponse($result);
	}

}
