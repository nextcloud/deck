<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Sharing;

use OC\Files\Filesystem;
use OCA\Deck\Cache\AttachmentCacheHelper;
use OCA\Deck\Service\ConfigService;
use OCA\Files_Sharing\BackgroundJob\ExternalShareScanJob;
use OCA\Files_Sharing\External\ExternalShare;
use OCA\Files_Sharing\External\Manager as ExternalShareManager;
use OCA\Files_Sharing\External\Mount as ExternalShareMount;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Federation\ICloudIdManager;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\ISetupManager;
use OCP\IDBConnection;
use OCP\IPreview;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

class RemoteShareHelper {
	public function __construct(
		private readonly IDBConnection $dbConnection,
		private readonly IManager $shareManager,
		private readonly ExternalShareManager $externalShareManager,
		private readonly AttachmentCacheHelper $attachmentCacheHelper,
		private readonly IUserManager $userManager,
		private readonly ICloudIdManager $cloudIdManager,
		private readonly ConfigService $configService,
		private readonly IJobList $jobList,
		private readonly IRootFolder $rootFolder,
		private readonly IPreview $preview,
		private readonly \OC\Files\Mount\Manager $mountManager,
		private readonly ISetupManager $setupManager,
	) {
	}

	public function cleanupRemoteSharesForAclDeletion(int $boardId, string $federatedCloudId): void {
		if (!$this->cloudIdManager->isValidCloudId($federatedCloudId)) {
			return;
		}

		$deckShareIds = $this->findDeckShareIdsForBoard($boardId);
		if ($deckShareIds === []) {
			return;
		}

		$this->deleteFederatedAttachmentShares($deckShareIds, $federatedCloudId);
		$this->deleteExternalSharesSharedByFederatedUser($deckShareIds, $federatedCloudId);
	}

	public function deleteExternalShare(IShare $share): void {
		$shareBy = $share->getSharedBy();

		if (!$this->cloudIdManager->isValidCloudId($shareBy)) {
			return;
		}

		$externalShare = $this->findRootExternalShare($share, $shareBy);
		if ($externalShare === false) {
			return;
		}

		$user = $this->userManager->get($externalShare->getUser());
		if ($user === null) {
			return;
		}

		$this->externalShareManager->declineShare($externalShare, $user);
	}

	public function findRootExternalShare(IShare $share, string $ownerCloudId): ExternalShare|false {
		$node = $share->getNode();
		$cloudId = $this->cloudIdManager->resolveCloudId($ownerCloudId);
		$mountPoint = str_replace('/' . $share->getShareOwner() . '/files', '', $node->getPath());

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id', 'refresh_token', 'mountpoint', 'user')
			->from('share_external')
			->andWhere($qb->expr()->eq('remote', $qb->createNamedParameter($cloudId->getRemote() . '/')))
			->andWhere($qb->expr()->eq('owner', $qb->createNamedParameter($cloudId->getUser())))
			->andWhere($qb->expr()->eq('mountpoint', $qb->createNamedParameter($mountPoint)))
			->setMaxResults(1);

		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			return false;
		}

		$user = $this->userManager->get($data['user']);
		if ($user === null) {
			return false;
		}

		return $this->externalShareManager->getShare((string)$data['id'], $user);
	}

	public function getRemoteFile(string $shareToken, string $remote, string $userId): ?array {
		$externalShare = $this->ensureAcceptRemoteShare($shareToken, $remote, $userId);
		$user = $this->userManager->get($externalShare->getUser());
		if ($user === null) {
			return null;
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$node = $userFolder->get($externalShare->getMountpoint());
		$files = $userFolder->getById($node->getId());

		if (count($files) === 0) {
			return [
				'path' => $externalShare->getMountpoint(),
				'fileid' => $node->getId(),
				'filesize' => $node->getSize(),
				'mimetype' => $node->getMimetype(),
				'info' => pathinfo($node->getName()),
				'hasPreview' => false,
			];
		}

		$file = array_shift($files);

		return [
			'path' => $userFolder->getRelativePath($file->getPath()),
			'fileid' => $file->getId(),
			'filesize' => $file->getSize(),
			'mimetype' => $file->getMimeType(),
			'info' => pathinfo($file->getName()),
			'hasPreview' => $this->preview->isAvailable($file),
		];
	}

	public function ensureAcceptRemoteShare(string $shareToken, string $remote, string $userId): ExternalShare {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id', 'accepted', 'user')
			->from('share_external')
			->where($qb->expr()->eq('refresh_token', $qb->createNamedParameter($shareToken)))
			->andWhere($qb->expr()->eq('user', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('remote', $qb->createNamedParameter($remote . '/')))
			->setMaxResults(1);

		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		$user = $this->userManager->get($data['user']);
		if ($user === null) {
			throw new ShareNotFound();
		}

		$externalShare = $this->externalShareManager->getShare((string)$data['id'], $user);
		if ($externalShare === false) {
			throw new ShareNotFound();
		}

		if ((int)$data['accepted'] === 0 && !$this->externalShareManager->acceptShare($externalShare, $user)) {
			throw new ShareNotFound();
		}

		$externalShare = $this->externalShareManager->getShare((string)$data['id'], $user);
		if ($externalShare === false) {
			throw new ShareNotFound();
		}

		if ($this->moveExternalShareToAttachmentFolder($externalShare, $user, $userId)) {
			$externalShare = $this->externalShareManager->getShare((string)$data['id'], $user);
			if ($externalShare === false) {
				throw new ShareNotFound();
			}

			// acceptShare() queues a scan job for the old mountpoint; queue one for the new path too.
			$this->jobList->add(ExternalShareScanJob::class, [$externalShare->getUser(), $externalShare->getMountpoint()]);
		}

		$this->mountExternalShare($externalShare, $user);
		return $externalShare;
	}

	public function mountExternalShare(ExternalShare $externalShare, IUser $user): ExternalShareMount {
		$this->setupManager->tearDown();
		$this->setupManager->setupForUser($user);

		$mountPoint = '/' . $user->getUID() . '/files/' . ltrim($externalShare->getMountpoint(), '/');
		$existingMount = $this->getMountedExternalShare($mountPoint);
		if ($existingMount !== null) {
			return $existingMount;
		}

		throw new GenericShareException('Failed to mount external share.');
	}

	private function moveExternalShareToAttachmentFolder(ExternalShare $externalShare, IUser $user, string $userId): bool {
		$attachmentFolder = trim($this->configService->getAttachmentFolder($userId), '/');
		if ($attachmentFolder === '') {
			return false;
		}

		$sourceMountPoint = '/' . ltrim($externalShare->getMountpoint(), '/');
		$attachmentPrefix = '/' . $attachmentFolder;
		if (str_starts_with($sourceMountPoint . '/', $attachmentPrefix . '/')) {
			return false;
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		if (!$userFolder->nodeExists($attachmentFolder)) {
			$userFolder->newFolder($attachmentFolder);
		}

		$attachmentNode = $userFolder->get($attachmentFolder);
		if (!$attachmentNode instanceof Folder) {
			return false;
		}

		$targetName = $attachmentNode->getNonExistingName($externalShare->getName());
		$targetMountPoint = Filesystem::normalizePath($attachmentPrefix . '/' . $targetName);
		$source = Filesystem::normalizePath('/' . $user->getUID() . '/files/' . ltrim($sourceMountPoint, '/'));
		$target = Filesystem::normalizePath('/' . $user->getUID() . '/files/' . ltrim($targetMountPoint, '/'));

		if ($source === $target || !$this->externalShareManager->setMountPoint($user, $source, $target)) {
			return false;
		}

		$externalShare->setMountpoint($targetMountPoint);
		return true;
	}

	private function getMountedExternalShare(string $mountPoint): ?ExternalShareMount {
		$normalizedMountPoint = rtrim(Filesystem::normalizePath($mountPoint), '/') . '/';
		$existingMounts = $this->mountManager->find($normalizedMountPoint);

		if (!is_iterable($existingMounts)) {
			$existingMounts = [$existingMounts];
		}

		foreach ($existingMounts as $existingMount) {
			if (!$existingMount instanceof ExternalShareMount) {
				continue;
			}

			$existingMountPoint = rtrim(Filesystem::normalizePath($existingMount->getMountPoint()), '/') . '/';
			if ($existingMountPoint === $normalizedMountPoint) {
				return $existingMount;
			}
		}

		return null;
	}

	/**
	 * @return int[]
	 */
	private function findDeckShareIdsForBoard(int $boardId): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('s.id')
			->from('share', 's')
			->innerJoin('s', 'deck_cards', 'c', $qb->expr()->eq($qb->expr()->castColumn('c.id', IQueryBuilder::PARAM_STR), 's.share_with'))
			->innerJoin('c', 'deck_stacks', 'st', $qb->expr()->eq('st.id', 'c.stack_id'))
			->where($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('st.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

		$cursor = $qb->executeQuery();
		$shareIds = [];
		while ($row = $cursor->fetchAssociative()) {
			$shareIds[] = (int)$row['id'];
		}
		$cursor->closeCursor();

		return $shareIds;
	}

	/**
	 * @param int[] $deckShareIds
	 */
	private function deleteFederatedAttachmentShares(array $deckShareIds, string $federatedCloudId): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_REMOTE, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($federatedCloudId)))
			->andWhere($qb->expr()->in('parent', $qb->createNamedParameter($deckShareIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$cursor = $qb->executeQuery();
		$shareIds = [];
		while ($row = $cursor->fetchAssociative()) {
			$shareIds[] = (int)$row['id'];
		}
		$cursor->closeCursor();

		if ($shareIds === []) {
			return;
		}

		$deleteQb = $this->dbConnection->getQueryBuilder();
		$deleteQb->delete('share')
			->where($deleteQb->expr()->in('id', $deleteQb->createNamedParameter($shareIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$deleteQb->executeStatement();
	}

	/**
	 * @param int[] $deckShareIds
	 */
	private function deleteExternalSharesSharedByFederatedUser(array $deckShareIds, string $federatedCloudId): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id', 'file_source', 'uid_owner', 'uid_initiator', 'share_with')
			->from('share')
			->where($qb->expr()->in('id', $qb->createNamedParameter($deckShareIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($federatedCloudId)));

		$cursor = $qb->executeQuery();
		$shareIds = [];
		$cardIds = [];
		while ($row = $cursor->fetchAssociative()) {
			$shareIds[] = (int)$row['id'];
			$cardIds[(int)$row['share_with']] = true;

			$files = $this->rootFolder->getById((int)$row['file_source']);
			if ($files === []) {
				continue;
			}

			$share = $this->shareManager->newShare();
			$share->setNode(array_shift($files));
			$share->setSharedBy((string)$row['uid_initiator']);
			$share->setShareOwner((string)$row['uid_owner']);
			$this->deleteExternalShare($share);
		}
		$cursor->closeCursor();

		if ($shareIds === []) {
			return;
		}

		$deleteQb = $this->dbConnection->getQueryBuilder();
		$deleteQb->delete('share')
			->where($deleteQb->expr()->in('id', $deleteQb->createNamedParameter($shareIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orWhere($deleteQb->expr()->in('parent', $deleteQb->createNamedParameter($shareIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$deleteQb->executeStatement();

		foreach (array_keys($cardIds) as $cardId) {
			$this->attachmentCacheHelper->clearAttachmentCount((int)$cardId);
		}
	}
}
