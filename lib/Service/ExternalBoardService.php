<?php

namespace OCA\Deck\Service;

use OCP\AppFramework\Http\DataResponse;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Federation\DeckFederationProxy;
use OCP\Federation\ICloudIdManager;
use OCP\IUserManager;

class ExternalBoardService {
	public function __construct(
		private  IUserManager $userManager,
		private ICloudIdManager $cloudIdManager,
		private DeckFederationProxy $proxy,
		private ?string $userId,
	) {
	}

	public function getExternalBoardFromRemote(Board $localBoard):DataResponse {
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . "/ocs/v2.php/apps/deck/api/v1.0/board/".$localBoard->getExternalId();
		$resp = $this->proxy->get($participantCloudId->getId(), $shareToken, $url);
		return new DataResponse($this->LocalizeRemoteBoard($this->proxy->getOcsData($resp), $localBoard));
	}
	public function getExternalStacksFromRemote(Board $localBoard):DataResponse {
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . "/ocs/v2.php/apps/deck/api/v1.0/stacks/".$localBoard->getExternalId();
		$resp = $this->proxy->get($participantCloudId->getId(), $shareToken, $url);
		return new DataResponse($this->LocalizeRemoteStacks($this->proxy->getOcsData($resp), $localBoard));
	}

	public function LocalizeRemoteStacks(array $stacks, Board $localBoard) {
		foreach ($stacks as $i => $stack) {
			$stack['boardId'] = $localBoard->getId();
			$stacks[$i] = $stack;
		}
		return $stacks;
	}
	public function LocalizeRemoteBoard(array $remoteBoard, Board $localBoard) {
		$remoteBoard['id'] = $localBoard->getId();
		$remoteBoard['stacks'] = $this->LocalizeRemoteStacks($remoteBoard['stacks'], $localBoard);
		return $remoteBoard;
	}

	public function createCardOnRemote(
		Board $localBoard,
		string $title,
		int $stackId,
		?string $type = 'plain',
		?int $order = 999,
		?string $description = '',
		$duedate = null,
		?array $users = [],
		?int $boardId = null
	): array {
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . "/ocs/v2.php/apps/deck/api/v1.0/cards";
		$params = [
			'title' => $title,
			'stackId' => $stackId,
			'type' => $type,
			'order' => $order,
			'owner' => $participantCloudId->getId(),
			'description' => $description,
			'duedate' => $duedate,
			'users' => $users,
			'boardId' => $localBoard->getExternalId(),
		];
		$resp = $this->proxy->post($participantCloudId->getId(), $shareToken, $url, $params);
		return $this->proxy->getOcsData($resp);
	}
}
