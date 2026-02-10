<?php

namespace OCA\Deck\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Exceptions\FederationDisabledException;
use OCA\Deck\Federation\DeckFederationProxy;
use OCP\AppFramework\Http\DataResponse;
use OCP\Federation\ICloudIdManager;
use OCP\IUserManager;

class ExternalBoardService {
	public function __construct(
		private IUserManager $userManager,
		private ICloudIdManager $cloudIdManager,
		private DeckFederationProxy $proxy,
		private ConfigService $configService,
		private ?string $userId,
	) {
	}

	private function ensureFederationEnabled(): void {
		if (!$this->configService->get('federationEnabled')) {
			throw new FederationDisabledException();
		}
	}

	public function getExternalBoardFromRemote(Board $localBoard):DataResponse {
		$this->ensureFederationEnabled();
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/board/' . $localBoard->getExternalId();
		$resp = $this->proxy->get($participantCloudId->getId(), $shareToken, $url);
		$ocs = $this->proxy->getOCSData($resp);
		return new DataResponse($this->LocalizeRemoteBoard($ocs, $localBoard));
	}
	public function getExternalStacksFromRemote(Board $localBoard):DataResponse {
		$this->ensureFederationEnabled();
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/stacks/' . $localBoard->getExternalId();
		$resp = $this->proxy->get($participantCloudId->getId(), $shareToken, $url);
		$ocs = $this->proxy->getOCSData($resp);
		return new DataResponse($this->LocalizeRemoteStacks($ocs, $localBoard));
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
		?int $boardId = null,
	): array {
		$this->ensureFederationEnabled();
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/cards';
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

	public function createStackOnRemote(
		Board $localBoard,
		string $title,
		int $order = 0,
	): array {
		$this->ensureFederationEnabled();
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/stacks';
		$params = [
			'title' => $title,
			'boardId' => $localBoard->getExternalId(),
			'order' => $order,
		];
		$resp = $this->proxy->post($participantCloudId->getId(), $shareToken, $url, $params);
		$stack = $this->proxy->getOcsData($resp);
		return $this->localizeRemoteStacks([$stack], $localBoard)[0];
	}

	public function deleteStackOnRemote(Board $localBoard, int $stackId): array {
		$this->ensureFederationEnabled();
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/stacks/' . $stackId;
		$resp = $this->proxy->delete($participantCloudId->getId(), $shareToken, $url, []);
		return $this->proxy->getOcsData($resp);
	}
}
