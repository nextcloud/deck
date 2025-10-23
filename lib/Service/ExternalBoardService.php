<?php

namespace OCA\Deck\Service;

use OCP\AppFramework\Http\DataResponse;
use OCA\Deck\Db\Board;
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
		$url = $ownerCloudId->getRemote() . "/ocs/v2.php/apps/deck/api/v1.0/board/".$localBoard->getExternalId()."?accessToken=".urlencode($shareToken);
		$resp = $this->proxy->get($participantCloudId->getId(), $shareToken, $url);
		return new DataResponse($this->proxy->getOcsData($resp));
	}
	public function getExternalStacksFromRemote(Board $localBoard):DataResponse {
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . "/ocs/v2.php/apps/deck/api/v1.0/stacks/".$localBoard->getExternalId()."?accessToken=".urlencode($shareToken);
		$resp = $this->proxy->get($participantCloudId->getId(), $shareToken, $url);
		return new DataResponse($this->proxy->getOcsData($resp));
	}
}
