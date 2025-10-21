<?php

namespace OCA\Deck\Federation;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\ChangeHelper;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\Notification\IManager as INotificationManager;
use Exception;

class DeckFederationProvider implements ICloudFederationProvider{
	public const PROVIDER_ID = 'deck';

	public function __construct(
		private readonly ICloudIdmanager $cloudIdManager,
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
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('deck');
		$notification->setUser($share->getShareWith());
		$notification->setDateTime(new \DateTime());
		$notification->setObject('remote-board-shared', (string) rand(0,9999999));
		$notification->setSubject('remote-board-shared',[$share->getResourceName(), $share->getSharedBy()]);

		$this->notificationManager->notify($notification);

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
		$acl->setPermissionEdit(false);
		$acl->setPermissionShare(false);
		$acl->setPermissionManage(false);
		$this->aclMapper->insert($acl);

		$this->changeHelper->boardChanged($insertedBoard->getId());
		return 'PLACE_HOLDER_ID';
	}

	public function notificationReceived($notificationType, $providerId, $notification): array {
		return [];
	}

	public function getSupportedShareTypes(): array {
		return ['user'];
	}
}
