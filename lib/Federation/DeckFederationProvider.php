<?php

namespace OCA\Deck\Federation;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\ChangeHelper;
use OCP\Common\Exception\NotFoundException;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\Notification\IManager as INotificationManager;
use Exception;

class DeckFederationProvider implements ICloudFederationProvider{
	public const PROVIDER_ID = 'deck';

	public function __construct(
		private readonly ICloudIdManager $cloudIdManager,
		private INotificationManager $notificationManager,
		private BoardMapper $boardMapper,
		private AclMapper $aclMapper,
		private ChangeHelper $changeHelper,
	){
	}

	public function getShareType(): string {
		return self::PROVIDER_ID;
	}

	public function shareReceived(ICloudFederationShare $share): string {

		$externalBoard = new Board();
		$externalBoard->setTitle($share->getResourceName());
		$externalBoard->setExternalId($share->getProviderId());
		$externalBoard->setOwner($share->getSharedBy());
		$externalBoard->setShareToken($share->getShareSecret());
		$insertedBoard = $this->boardMapper->insert($externalBoard);

		$acl = new Acl();
		$acl->setBoardId($insertedBoard->getId());
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant($share->getShareWith());
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(false);
		$acl->setPermissionManage(true);
		$this->aclMapper->insert($acl);

		$this->changeHelper->boardChanged($insertedBoard->getId());

		$notification = $this->notificationManager->createNotification();
		$notification->setApp('deck');
		$notification->setUser($share->getShareWith());
		$notification->setDateTime(new \DateTime());
		$notification->setObject('remote-board-shared', $insertedBoard->getId());
		$notification->setSubject('remote-board-shared',[$share->getResourceName(), $share->getSharedBy()]);

		$this->notificationManager->notify($notification);
		return 'PLACE_HOLDER_ID';
	}

	public function notificationReceived($notificationType, $providerId, $notification): array {
		switch ($notificationType) {
			case "update-permissions":
				$localBoards = $this->boardMapper->findByExternalId($providerId);
				foreach ($localBoards as $board) {
					if ($board->getShareToken() === $notification["sharedSecret"]) {
						$localBoard = $board;
					}
				}
				if (!isset($localBoard)) {
					throw new NotFoundException("Board not found for provider ID: " . $providerId);
				}
				$localParticipant = $this->cloudIdManager->resolveCloudId($notification[0]["participant"])->getUser();
				$acls = $this->aclMapper->findAll($localBoard->getId());
				foreach ($acls as $acl) {
					if ($acl->getParticipant() === $localParticipant) {
						$localAcl = $acl;
						break;
					}
				}
				if (!isset($localAcl)) {
					throw new NotFoundException("ACL entry not found for participant: " . $localParticipant);
				}
				$acl->setPermissionEdit($notification[0]["permissionEdit"]);
				$acl->setPermissionManage($notification[0]["permissionManage"]);
				$acl->setPermissionShare($notification[0]["permissionShare"]);
				$this->aclMapper->update($acl);
				break;
			default:
				throw new Exception("Unknown notification type: " . $notificationType);
		}
		return [];
	}

	public function getSupportedShareTypes(): array {
		return ['user'];
	}
}
