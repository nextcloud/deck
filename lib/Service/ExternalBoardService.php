<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use Exception;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\FederatedUser;
use OCA\Deck\Db\User;
use OCA\Deck\Federation\DeckFederationProxy;
use OCA\Deck\Model\OptionalNullableValue;
use OCP\AppFramework\Http\DataResponse;
use OCP\Federation\ICloudIdManager;
use OCP\IURLGenerator;
use OCP\IUserManager;

class ExternalBoardService {
	public function __construct(
		private IUserManager $userManager,
		private ICloudIdManager $cloudIdManager,
		private DeckFederationProxy $proxy,
		private ConfigService $configService,
		private BoardService $boardService,
		private PermissionService $permissionService,
		private BoardMapper $boardMapper,
		private IURLGenerator $urlGenerator,
		private ?string $userId,
	) {
	}

	public function getExternalBoardFromRemote(Board $localBoard):DataResponse {
		$this->configService->ensureFederationEnabled();
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/board/' . $localBoard->getExternalId();
		$resp = $this->proxy->get($participantCloudId->getId(), $shareToken, $url);
		$ocs = $this->proxy->getOCSData($resp);
		return new DataResponse($this->LocalizeRemoteBoard($ocs, $localBoard));
	}
	public function getExternalStacksFromRemote(Board $localBoard):DataResponse {
		$this->configService->ensureFederationEnabled();
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/stacks/' . $localBoard->getExternalId();
		$resp = $this->proxy->get($participantCloudId->getId(), $shareToken, $url);
		$ocs = $this->proxy->getOCSData($resp);
		return new DataResponse($this->LocalizeRemoteStacks($ocs, $localBoard));
	}

	public function localizeRemoteUser(Board $localBoard, array $user): array|User|FederatedUser|null {
		// skip invalid users
		if (!$user['uid']) {
			return null;
		}
		// if it's already a valid cloud id the user originates from a third instance and we pass it as is
		if ($this->cloudIdManager->isValidCloudId($user['uid'])) {
			if ($user['remote'] == $this->urlGenerator->getBaseUrl()) {
				// local user from remote: return as local user
				$localuid = $this->cloudIdManager->resolveCloudId($user['uid'])->getUser();
				return new User($localuid, $this->userManager);
			}
			return new FederatedUser($this->cloudIdManager->resolveCloudId($user['uid']));
		}
		// if it's not a valid cloud id: it originates from the remote instance and we send it out as a federated user
		$owner = $localBoard->resolveOwner(); // retrieve owner to get the remote
		if ($owner instanceof FederatedUser) {
			return new FederatedUser($this->cloudIdManager->getCloudId($user['uid'], $owner->getCloudId()->getRemote()));
		}
		throw new Exception('Owner of the board must be a federated user to localize remote users');
	}

	public function LocalizeRemoteStacks(array $stacks, Board $localBoard) {
		foreach ($stacks as $i => $stack) {
			$stack['boardId'] = $localBoard->getId();
			foreach ($stack['cards'] as $j => $card) {
				$stack['cards'][$j]['assignedUsers'] = array_map(function ($assignment) use ($localBoard) {
					$assignment['participant'] = $this->localizeRemoteUser($localBoard, $assignment['participant']);
					return $assignment;
				}, $card['assignedUsers']);
			}
			$stacks[$i] = $stack;
		}
		return $stacks;
	}
	public function LocalizeRemoteBoard(array $remoteBoard, Board $localBoard) {
		$remoteBoard['id'] = $localBoard->getId();
		$remoteBoard['stacks'] = $this->LocalizeRemoteStacks($remoteBoard['stacks'], $localBoard);
		$remoteBoard['owner'] = $localBoard->resolveOwner();
		$remoteBoard['acl'] = $localBoard->getAcl();
		$remoteBoard['permissions'] = $localBoard->getPermissions();
		$remoteBoard['users'] = $this->localizeRemoteUsers($remoteBoard['users'], $localBoard);
		return $remoteBoard;
	}

	public function localizeRemoteUsers(array $users, Board $localBoard) {
		$localizedUsers = [];
		foreach ($users as $i => $user) {
			$localizedUsers[] = $this->localizeRemoteUser($localBoard, $user);
		}

		return $localizedUsers;
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
	): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_EDIT, $this->userId, false, false);
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

	public function updateCardOnRemote(
		Board $localBoard,
		int $cardId,
		string $title,
		int $stackId,
		string $type,
		string $owner,
		string $description = '',
		int $order = 0,
		?string $duedate = null,
		?int $deletedAt = null,
		?bool $archived = null,
		?OptionalNullableValue $done = null,
	): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_EDIT, $this->userId, false, false);
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/cards/' . $cardId;
		$params = [
			'id' => $cardId,
			'title' => $title,
			'stackId' => $stackId,
			'type' => $type,
			'owner' => $owner,
			'description' => $description,
			'order' => $order,
			'duedate' => $duedate,
			'deletedAt' => $deletedAt,
			'archived' => $archived,
			'done' => $done->getValue() ?? null,
			'boardId' => $localBoard->getExternalId(),
		];
		$resp = $this->proxy->put($participantCloudId->getId(), $shareToken, $url, $params);
		return $this->proxy->getOcsData($resp);
	}

	public function assignLabelOnRemote(Board $localBoard, int $cardId, int $labelId): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_EDIT, $this->userId, false, false);
		$shareToken = $localBoard->getShareToken();
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/cards/' . $cardId . '/label/' . $labelId;
		$resp = $this->proxy->post($ownerCloudId->getId(), $shareToken, $url, [
			'boardId' => $localBoard->getExternalId(),
		]);
		return $this->proxy->getOcsData($resp);
	}

	public function removeLabelOnRemote(Board $localBoard, int $cardId, int $labelId): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_EDIT, $this->userId, false, false);
		$shareToken = $localBoard->getShareToken();
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/cards/' . $cardId . '/label/' . $labelId;
		$resp = $this->proxy->delete($ownerCloudId->getId(), $shareToken, $url, [
			'boardId' => $localBoard->getExternalId(),
		]);
		return $this->proxy->getOcsData($resp);
	}

	public function assignUserOnRemote(Board $localBoard, int $cardId, string $userId, int $type = 0): array {
		$this->configService->ensureFederationEnabled();

		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		[$resolvedUserId, $resolvedType] = $this->resolveUserIdForRemote($userId, $ownerCloudId);

		$shareToken = $localBoard->getShareToken();
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/cards/' . $cardId . '/assign';
		$resp = $this->proxy->post($ownerCloudId->getId(), $shareToken, $url, [
			'userId' => $resolvedUserId,
			'type' => $resolvedType,
			'boardId' => $localBoard->getExternalId(),
		]);
		$result = $this->proxy->getOcsData($resp);
		if (isset($result['participant'])) {
			$result['participant'] = $this->localizeRemoteUser($localBoard, $result['participant']);
		}
		return $result;
	}

	public function unAssignUserOnRemote(Board $localBoard, int $cardId, string $userId, int $type = 0): array {
		$this->configService->ensureFederationEnabled();

		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		[$resolvedUserId, $resolvedType] = $this->resolveUserIdForRemote($userId, $ownerCloudId);

		$shareToken = $localBoard->getShareToken();
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/cards/' . $cardId . '/unassign';
		$resp = $this->proxy->put($ownerCloudId->getId(), $shareToken, $url, [
			'userId' => $resolvedUserId,
			'type' => $resolvedType,
			'boardId' => $localBoard->getExternalId(),
		]);
		return $this->proxy->getOcsData($resp);
	}
	/**
	 * Resolves the userId and type before sending it to remote instances
	 * @param string $userId
	 * @param $ownerCloudId
	 * @return array [userId, type]
	 */
	private function resolveUserIdForRemote(string $userId, $ownerCloudId): array {
		if ($this->cloudIdManager->isValidCloudId($userId)) {
			$cloudId = $this->cloudIdManager->resolveCloudId($userId);
			// assignee's origin is the same as the board owner's origin: send as local user
			if ($cloudId->getRemote() === $ownerCloudId->getRemote()) {
				return [$cloudId->getUser(), Assignment::TYPE_USER];
			}
		}
		// local user for us = remote user for remote
		return [$this->cloudIdManager->getCloudId($userId, null)->getId(), Assignment::TYPE_REMOTE];
	}

	public function createStackOnRemote(
		Board $localBoard,
		string $title,
		int $order = 0,
	): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_MANAGE, $this->userId, false, false);
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

	public function getRemoteCapabilities(Board $localBoard): array {
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/cloud/capabilities';
		$resp = $this->proxy->get('', '', $url);
		$data = $this->proxy->getOCSData($resp);
		return $data['capabilities']['deck'] ?? [];
	}

	public function remoteSupportsCapability(Board $localBoard, string $capability): bool {
		$capabilities = $this->getRemoteCapabilities($localBoard);
		return !empty($capabilities[$capability]);
	}

	public function setDoneStackOnRemote(Board $localBoard, int $stackId, bool $isDone): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_MANAGE, $this->userId, false, false);

		if (!$this->remoteSupportsCapability($localBoard, 'supportsDoneColumn')) {
			throw new \Exception('Remote server does not support the done column feature');
		}

		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/stacks/' . $stackId . '/done';
		$params = [
			'boardId' => $localBoard->getExternalId(),
			'isDone' => $isDone,
		];
		$resp = $this->proxy->put($participantCloudId->getId(), $shareToken, $url, $params);
		return $this->proxy->getOcsData($resp);
	}

	public function deleteStackOnRemote(Board $localBoard, int $stackId): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_EDIT, $this->userId, false, false);
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/stacks/' . $stackId;
		$resp = $this->proxy->delete($participantCloudId->getId(), $shareToken, $url, []);
		return $this->proxy->getOcsData($resp);
	}

	public function reorderCardOnRemote(Board $localBoard, int $cardId, int $stackId, int $order): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_EDIT, $this->userId, false, false);
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/cards/' . $cardId . '/reorder';
		$params = [
			'stackId' => $stackId,
			'order' => $order,
			'boardId' => $localBoard->getExternalId(),
		];
		$resp = $this->proxy->put($participantCloudId->getId(), $shareToken, $url, $params);
		return $this->proxy->getOcsData($resp);
	}

	public function reorderStackOnRemote(Board $localBoard, int $stackId, int $order): array {
		$this->configService->ensureFederationEnabled();
		$this->permissionService->checkPermission($this->boardMapper, $localBoard->getId(), Acl::PERMISSION_EDIT, $this->userId, false, false);
		$shareToken = $localBoard->getShareToken();
		$participantCloudId = $this->cloudIdManager->getCloudId($this->userId, null);
		$ownerCloudId = $this->cloudIdManager->resolveCloudId($localBoard->getOwner());
		$url = $ownerCloudId->getRemote() . '/ocs/v2.php/apps/deck/api/v1.0/stacks/' . $stackId . '/reorder';
		$params = [
			'order' => $order,
			'boardId' => $localBoard->getExternalId(),
		];
		$resp = $this->proxy->put($participantCloudId->getId(), $shareToken, $url, $params);
		return $this->proxy->getOcsData($resp);
	}
}
