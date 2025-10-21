<?php

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\StackService;
use OCA\Deck\Service\ExternalBoardService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class NewBoardController extends OCSController{
	public function __construct(
		string $appName,
		IRequest $request,
		private BoardService $boardService,
		private ExternalBoardService $externalBoardService,
		private LoggerInterface $logger,
		private StackService $stackService,
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
		$localBoard = $this->boardService->find($boardId, true, true, $this->request->getParam('accessToken'));
		if($localBoard->getExternalId() !== null) {
			return $this->externalBoardService->getExternalBoardFromRemote($localBoard);
		}
		// Board on other instance -> get it from other instance
		return new DataResponse($localBoard);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function stacks(int $boardId): DataResponse{
		$localBoard = $this->boardService->find($boardId, true, true, $this->request->getParam('accessToken'));
		// Board on other instance -> get it from other instance
		if($localBoard->getExternalId() !== null) {
			return $this->externalBoardService->getExternalStacksFromRemote($localBoard);
		} else {
			return new DataResponse($this->stackService->findAll($boardId));
		}
	}
}
