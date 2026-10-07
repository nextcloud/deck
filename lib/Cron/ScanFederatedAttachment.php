<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Cron;

use OCA\Deck\Sharing\DeckShareProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCA\Files_Sharing\External\Manager as ExternalShareManager;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;

class ScanFederatedAttachment extends QueuedJob {
	public function __construct(
		private readonly IJobList $jobList,
		private readonly ExternalShareManager $externalShareManager,
		private readonly IUserManager $userManager,
		private readonly IRootFolder $rootFolder,
		private readonly IManager $shareManager,
		private readonly DeckShareProvider $deckShareProvider,
		ITimeFactory $time,
	) {
		parent::__construct($time);
	}

	#[\Override]
	protected function run($argument): void {
		$cardId = $argument['cardId'];
		$shareId = $argument['shareId'];
		$userId = $argument['userId'];
		$user = $this->userManager->get($userId);

		if ($user === null) {
			return;
		}

		$externalShare = $this->externalShareManager->getShare((string)$shareId, $user);

		if ($externalShare === false) {
			return;
		}

		$this->deckShareProvider->mountExternalShare($externalShare, $user);
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());

		$node = $userFolder->get($externalShare->getMountpoint());
		$files = $userFolder->getById($node->getId());

		if (count($files) === 0) {
			// Retry later, maybe the share is not yet mounted
			$this->jobList->add(ScanFederatedAttachment::class, [
				'cardId' => $cardId,
				'shareId' => $shareId,
				'userId' => $userId,
			]);

			// Scan the files to make sure the share is mounted and the files are available
			exec('php ' . \OC::$SERVERROOT . '/occ files:scan --path="/' . $userId . '/files' . $externalShare->getMountpoint() . '"');
			return;
		}

		$share = $this->shareManager->newShare();
		$share->setNode($node);
		$share->setShareType(ISHARE::TYPE_DECK);
		$share->setSharedWith((string)$cardId);
		$share->setPermissions(Constants::PERMISSION_READ);
		$share->setSharedBy($externalShare->getUser());
		$remote = substr($externalShare->getRemote(), 0, strrpos($externalShare->getRemote(), '/'));
		$share->setShareOwner($externalShare->getOwner() . '@' . $remote);
		$share = $this->shareManager->createShare($share);

		var_dump('Created share for cardId: ' . $cardId . ' and shareId: ' . $shareId . ' with shareId: ' . $share->getId() . ' and nodeId: ' . $node->getId());
	}
}
