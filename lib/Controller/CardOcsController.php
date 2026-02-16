<?php

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\ExternalBoardService;
use OCA\Deck\Service\StackService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CardOcsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CardService $cardService,
		private StackService $stackService,
		private BoardService $boardService,
		private ExternalBoardService $externalBoardService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequestHeader(name: 'x-nextcloud-federation', description: 'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request', indirect: true)]
	public function create(string $title, int $stackId, ?int $boardId = null, ?string $type = 'plain', ?string $owner = null, ?int $order = 999, ?string $description = '', $duedate = null, ?array $labels = [], ?array $users = []) {
		if ($boardId) {
			$board = $this->boardService->find($boardId, false);
			if ($board->getExternalId()) {
				$card = $this->externalBoardService->createCardOnRemote($board, $title, $stackId, $type, $order, $description, $duedate, $users);
				return new DataResponse($card);
			}
		}

		if (!$owner) {
			$owner = $this->userId;
		}
		$card = $this->cardService->create($title, $stackId, $type, $order, $owner, $description, $duedate);

		// foreach ($labels as $label) {
		// 	$this->assignLabel($card->getId(), $label);
		// }

		// foreach ($users as $user) {
		// 	$this->assignmentService->assignUser($card->getId(), $user['id'], $user['type']);
		// }

		return new DataResponse($card);
	}

}
