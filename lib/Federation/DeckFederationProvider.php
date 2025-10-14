<?php

namespace OCA\Deck\Federation;
use OCA\Deck\Db\ExternalBoard;
use OCA\Deck\Db\ExternalBoardMapper;
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
		private ExternalBoardMapper $externalBoardMapper,
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

		$externalBoard = new ExternalBoard();
		$externalBoard->setTitle($share->getResourceName());
		$externalBoard->setExternalId($share->getProviderId());
		$externalBoard->setOwner($share->getSharedBy());
		$externalBoard->setParticipant($share->getShareWith());
		$this->externalBoardMapper->insert($externalBoard);
		return 'PLACE_HOLDER_ID';
	}

	public function notificationReceived($notificationType, $providerId, $notification): array {
		return [];
	}

	public function getSupportedShareTypes(): array {
		return ['user'];
	}
}
