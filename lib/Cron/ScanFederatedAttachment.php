<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Cron;

use OCA\Deck\Sharing\RemoteShareHelper;
use OCA\Files_Sharing\External\Manager as ExternalShareManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Constants;
use OCP\Federation\ICloudIdManager;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;

class ScanFederatedAttachment extends QueuedJob {
	public const MAX_RETRIES = 10;

	public function __construct(
		private readonly IJobList $jobList,
		private readonly ExternalShareManager $externalShareManager,
		private readonly IUserManager $userManager,
		private readonly IRootFolder $rootFolder,
		private readonly IManager $shareManager,
		private readonly RemoteShareHelper $remoteShareHelper,
		private readonly ICloudIdManager $cloudIdManager,
		ITimeFactory $time,
	) {
		parent::__construct($time);
	}

	#[\Override]
	protected function run($argument): void {
		$cardId = $argument['cardId'];
		$shareId = $argument['shareId'];
		$userId = $argument['userId'];
		$attempt = $argument['attempt'] ?? 1;
		$user = $this->userManager->get($userId);

		if ($user === null) {
			return;
		}

		$externalShare = $this->externalShareManager->getShare((string)$shareId, $user);

		if ($externalShare === false) {
			return;
		}

		$this->remoteShareHelper->mountExternalShare($externalShare, $user);
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());

		$node = $userFolder->get($externalShare->getMountpoint());
		$files = $userFolder->getById($node->getId());

		if (count($files) === 0) {
			$node->getStorage()->getScanner()->scan($node->getInternalPath());
			$files = $userFolder->getById($node->getId());
		}
		if (count($files) === 0) {
			$attempt++;
			if ($attempt > self::MAX_RETRIES) {
				return;
			}
			// Retry later, maybe the share is not yet mounted
			$this->jobList->add(ScanFederatedAttachment::class, [
				'cardId' => $cardId,
				'shareId' => $shareId,
				'userId' => $userId,
				'attempt' => $attempt,
			]);
			return;
		}

		$share = $this->shareManager->newShare();
		$share->setNode($node);
		$share->setShareType(ISHARE::TYPE_DECK);
		$share->setSharedWith((string)$cardId);
		$share->setPermissions(Constants::PERMISSION_READ);
		$federatedId = $this->cloudIdManager->getCloudId($externalShare->getOwner(), $externalShare->getRemote())->getId();
		$share->setSharedBy($federatedId);
		$share->setShareOwner($externalShare->getUser());
		$this->shareManager->createShare($share);
	}
}
