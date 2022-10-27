<?php
/*
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\Deck\Sharing;

use OC\Files\Cache\Cache;
use OCA\Deck\Cache\AttachmentCacheHelper;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\User;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

/** Taken from the talk shareapicontroller helper */
interface IShareProviderBackend {
	public function parseDate(string $expireDate): \DateTime;
	public function createShare(IShare $share, string $shareWith, int $permissions, string $expireDate): void;
	public function formatShare(IShare $share): array;
	public function canAccessShare(IShare $share, string $user): bool;
}

class DeckShareProvider implements \OCP\Share\IShareProvider {
	public const DECK_FOLDER = '/Deck';
	public const DECK_FOLDER_PLACEHOLDER = '/{DECK_PLACEHOLDER}';

	public const SHARE_TYPE_DECK_USER = IShare::TYPE_DECK_USER;

	private IDBConnection $dbConnection;
	private IManager $shareManager;
	private AttachmentCacheHelper $attachmentCacheHelper;
	private BoardMapper $boardMapper;
	private CardMapper $cardMapper;
	private PermissionService $permissionService;
	private ITimeFactory $timeFactory;
	private IL10N $l;
	private IMimeTypeLoader $mimeTypeLoader;
	private ?string $userId;

	public function __construct(
		IDBConnection $connection,
		IManager $shareManager,
		BoardMapper $boardMapper,
		CardMapper $cardMapper,
		PermissionService $permissionService,
		AttachmentCacheHelper $attachmentCacheHelper,
		IL10N $l,
		ITimeFactory $timeFactory,
		IMimeTypeLoader $mimeTypeLoader,
		?string $userId
	) {
		$this->dbConnection = $connection;
		$this->shareManager = $shareManager;
		$this->boardMapper = $boardMapper;
		$this->cardMapper = $cardMapper;
		$this->attachmentCacheHelper = $attachmentCacheHelper;
		$this->permissionService = $permissionService;
		$this->l = $l;
		$this->timeFactory = $timeFactory;
		$this->mimeTypeLoader = $mimeTypeLoader;
		$this->userId = $userId;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		// Register listeners to clean up shares when card/board is deleted
	}

	/**
	 * @inheritDoc
	 */
	public function identifier() {
		return 'deck';
	}

	/**
	 * @inheritDoc
	 */
	public function create(IShare $share) {
		$cardId = $share->getSharedWith();
		$boardId = $this->cardMapper->findBoardId($cardId);
		$valid = $boardId !== null;
		try {
			$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_EDIT);
		} catch (NoPermissionException $e) {
			$valid = false;
		}

		try {
			$board = $this->boardMapper->find($boardId);
			$valid = $valid && !$board->getArchived();
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			$valid = false;
		}

		if (!$valid) {
			throw new GenericShareException('Card not found', $this->l->t('Card not found'), 404);
		}

		$existingShares = $this->getSharesByPath($share->getNode());
		foreach ($existingShares as $existingShare) {
			if ($existingShare->getSharedWith() === $share->getSharedWith()) {
				throw new GenericShareException('Already shared', $this->l->t('Path is already shared with this card'), 403);
			}
		}

		// Skipping token generation since we don't have public sharing in deck yet
		/*$share->setToken(
			$this->secureRandom->generate(
				15, // \OC\Share\Constants::TOKEN_LENGTH
				\OCP\Security\ISecureRandom::CHAR_HUMAN_READABLE
			)
		);*/

		$shareId = $this->addShareToDB(
			$share->getSharedWith(),
			$share->getSharedBy(),
			$share->getShareOwner(),
			$share->getNodeType(),
			$share->getNodeId(),
			$share->getTarget(),
			$share->getPermissions(),
			$share->getToken() ?? '',
			$share->getExpirationDate()
		);
		$data = $this->getRawShare($shareId);

		$this->attachmentCacheHelper->clearAttachmentCount((int)$cardId);

		return $this->createShareObject($data);
	}

	/**
	 * Add share to the database and return the ID
	 *
	 * @param string $shareWith
	 * @param string $sharedBy
	 * @param string $shareOwner
	 * @param string $itemType
	 * @param int $itemSource
	 * @param string $target
	 * @param int $permissions
	 * @param string $token
	 * @param \DateTime|null $expirationDate
	 * @return int
	 */
	private function addShareToDB(
		string $shareWith,
		string $sharedBy,
		string $shareOwner,
		string $itemType,
		int $itemSource,
		string $target,
		int $permissions,
		string $token,
		?\DateTime $expirationDate
	): int {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(IShare::TYPE_DECK))
			->setValue('share_with', $qb->createNamedParameter($shareWith))
			->setValue('uid_initiator', $qb->createNamedParameter($sharedBy))
			->setValue('uid_owner', $qb->createNamedParameter($shareOwner))
			->setValue('item_type', $qb->createNamedParameter($itemType))
			->setValue('item_source', $qb->createNamedParameter($itemSource))
			->setValue('file_source', $qb->createNamedParameter($itemSource))
			->setValue('file_target', $qb->createNamedParameter($target))
			->setValue('permissions', $qb->createNamedParameter($permissions))
			->setValue('token', $qb->createNamedParameter($token))
			->setValue('stime', $qb->createNamedParameter($this->timeFactory->getTime()));

		if ($expirationDate !== null) {
			$qb->setValue('expiration', $qb->createNamedParameter($expirationDate, 'datetime'));
		}

		$qb->executeStatement();

		return $qb->getLastInsertId();
	}

	/**
	 * Get database row of the given share
	 *
	 * @param int $id
	 * @return array
	 * @throws ShareNotFound
	 */
	private function getRawShare(int $id): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		return $data;
	}

	/**
	 * Create a share object from a database row
	 *
	 * @param array $data
	 * @return IShare
	 */
	private function createShareObject(array $data): IShare {
		$share = $this->shareManager->newShare();
		$share->setId($data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target'])
			->setStatus((int)$data['accepted'])
			->setToken($data['token']);

		$shareTime = $this->timeFactory->getDateTime();
		$shareTime->setTimestamp((int)$data['stime']);
		$share->setShareTime($shareTime);
		$share->setSharedWith($data['share_with']);

		$share->setSharedBy($data['uid_initiator']);
		$share->setShareOwner($data['uid_owner']);

		if ($data['expiration'] !== null) {
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $data['expiration']);
			if ($expiration !== false) {
				$share->setExpirationDate($expiration);
			}
		}

		$share->setNodeId((int)$data['file_source']);
		$share->setNodeType($data['item_type']);

		$share->setProviderId($this->identifier());

		if (isset($data['f_permissions'])) {
			$entryData = $data;
			$entryData['permissions'] = $entryData['f_permissions'];
			$entryData['parent'] = $entryData['f_parent'];
			$share->setNodeCacheEntry(Cache::cacheEntryFromData($entryData, $this->mimeTypeLoader));
		}
		return $share;
	}

	private function applyBoardPermission($share, $permissions, $userId) {
		try {
			$this->permissionService->checkPermission($this->cardMapper, $share->getSharedWith(), Acl::PERMISSION_EDIT, $userId);
		} catch (NoPermissionException $e) {
			$permissions &= Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE;
			$permissions &= Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE;
			$permissions &= Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE;
		}

		try {
			$this->permissionService->checkPermission($this->cardMapper, $share->getSharedWith(), Acl::PERMISSION_SHARE, $userId);
		} catch (NoPermissionException $e) {
			$permissions &= Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE;
		}
		$share->setPermissions($permissions);
	}
	/**
	 * @inheritDoc
	 */
	public function update(IShare $share) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
			->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
			->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
			->set('permissions', $qb->createNamedParameter($share->getPermissions()))
			->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
			->execute();

		/*
		 * Update all user defined group shares
		 */
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
			->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
			->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
			->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
			->execute();

		/*
		 * Now update the permissions for all children that have not set it to 0
		 */
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
			->andWhere($qb->expr()->neq('permissions', $qb->createNamedParameter(0)))
			->set('permissions', $qb->createNamedParameter($share->getPermissions()))
			->execute();

		return $share;
	}

	/**
	 * @inheritDoc
	 */
	public function delete(IShare $share) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())));

		$qb->orWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())));

		$qb->execute();

		$this->attachmentCacheHelper->clearAttachmentCount((int)$share->getSharedWith());
	}

	/**
	 * @inheritDoc
	 */
	public function deleteFromSelf(IShare $share, $recipient) {
		// Check if there is a deck_user share
		$qb = $this->dbConnection->getQueryBuilder();
		$stmt = $qb->select(['id', 'permissions'])
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_DECK_USER)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($recipient)))
			->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->execute();

		$data = $stmt->fetch();
		$stmt->closeCursor();

		if ($data === false) {
			// No userroom share yet. Create one.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->insert('share')
				->values([
					'share_type' => $qb->createNamedParameter(self::SHARE_TYPE_DECK_USER),
					'share_with' => $qb->createNamedParameter($recipient),
					'uid_owner' => $qb->createNamedParameter($share->getShareOwner()),
					'uid_initiator' => $qb->createNamedParameter($share->getSharedBy()),
					'parent' => $qb->createNamedParameter($share->getId()),
					'item_type' => $qb->createNamedParameter($share->getNodeType()),
					'item_source' => $qb->createNamedParameter($share->getNodeId()),
					'file_source' => $qb->createNamedParameter($share->getNodeId()),
					'file_target' => $qb->createNamedParameter($share->getTarget()),
					'permissions' => $qb->createNamedParameter(0),
					'stime' => $qb->createNamedParameter($share->getShareTime()->getTimestamp()),
				])->execute();
		} elseif ($data['permissions'] !== 0) {
			// Already a userroom share. Update it.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->update('share')
				->set('permissions', $qb->createNamedParameter(0))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
				->execute();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function restore(IShare $share, string $recipient): IShare {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('permissions')
			->from('share')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($share->getId()))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		$originalPermission = $data['permissions'];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->set('permissions', $qb->createNamedParameter($originalPermission))
			->where(
				$qb->expr()->eq('parent', $qb->createNamedParameter($share->getId()))
			)->andWhere(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_DECK_USER))
			)->andWhere(
				$qb->expr()->eq('share_with', $qb->createNamedParameter($recipient))
			);

		$qb->execute();

		return $this->getShareById((int)$share->getId(), $recipient);
	}

	/**
	 * @inheritDoc
	 */
	public function move(IShare $share, $recipient) {
		// Check if there is a deck user share
		$qb = $this->dbConnection->getQueryBuilder();
		$stmt = $qb->select('id')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_DECK_USER)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($recipient)))
			->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->setMaxResults(1)
			->execute();

		$data = $stmt->fetch();
		$stmt->closeCursor();

		if ($data === false) {
			// No deck user share yet. Create one.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->insert('share')
				->values([
					'share_type' => $qb->createNamedParameter(self::SHARE_TYPE_DECK_USER),
					'share_with' => $qb->createNamedParameter($recipient),
					'uid_owner' => $qb->createNamedParameter($share->getShareOwner()),
					'uid_initiator' => $qb->createNamedParameter($share->getSharedBy()),
					'parent' => $qb->createNamedParameter($share->getId()),
					'item_type' => $qb->createNamedParameter($share->getNodeType()),
					'item_source' => $qb->createNamedParameter($share->getNodeId()),
					'file_source' => $qb->createNamedParameter($share->getNodeId()),
					'file_target' => $qb->createNamedParameter($share->getTarget()),
					'permissions' => $qb->createNamedParameter($share->getPermissions()),
					'stime' => $qb->createNamedParameter($share->getShareTime()->getTimestamp()),
				])->executeStatement();
		} else {
			// Already a userroom share. Update it.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->update('share')
				->set('file_target', $qb->createNamedParameter($share->getTarget()))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
				->executeStatement();
		}

		return $share;
	}

	/**
	 * @inheritDoc
	 * @returns
	 */
	public function getSharesInFolder($userId, Folder $node, $reshares, $shallow = true) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share', 's')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('folder'))
			))
			->andWhere(
				$qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK))
			);

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('s.uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('s.uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('s.uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		$qb->innerJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'));
		if ($shallow) {
			$qb->andWhere($qb->expr()->eq('f.parent', $qb->createNamedParameter($node->getId())));
		} else {
			$qb->andWhere($qb->expr()->like('f.path', $qb->createNamedParameter($this->dbConnection->escapeLikeParameter($node->getInternalPath()) . '/%')));
		}

		$qb->orderBy('s.id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[$data['fileid']][] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritDoc
	 */
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_DECK)));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
		}

		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}

		$qb->setFirstResult($offset);
		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritDoc
	 * @throws ShareNotFound
	 */
	public function getShareById($id, $recipientId = null) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('s.*',
			'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
			'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
			'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
		)
			->selectAlias('st.id', 'storage_string_id')
			->from('share', 's')
			->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
			->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
			->where($qb->expr()->eq('s.id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		if (!$this->isAccessibleResult($data)) {
			throw new ShareNotFound();
		}

		$share = $this->createShareObject($data);

		if ($recipientId !== null) {
			$share = $this->resolveSharesForRecipient([$share], $recipientId)[0];
		}

		return $share;
	}

	/**
	 * Returns each given share as seen by the given recipient.
	 *
	 * If the recipient has not modified the share the original one is returned
	 * instead.
	 *
	 * @param IShare[] $shares
	 * @param string $userId
	 * @return IShare[]
	 */
	private function resolveSharesForRecipient(array $shares, string $userId): array {
		$result = [];

		$start = 0;
		while (true) {
			/** @var IShare[] $shareSlice */
			$shareSlice = array_slice($shares, $start, 1000);
			$start += 1000;

			if ($shareSlice === []) {
				break;
			}

			/** @var int[] $ids */
			$ids = [];
			/** @var IShare[] $shareMap */
			$shareMap = [];

			foreach ($shareSlice as $share) {
				$ids[] = (int)$share->getId();
				$shareMap[$share->getId()] = $share;
			}

			$qb = $this->dbConnection->getQueryBuilder();

			$query = $qb->select('*')
				->from('share')
				->where($qb->expr()->in('parent', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				));

			$stmt = $query->execute();

			while ($data = $stmt->fetch()) {
				$this->applyBoardPermission($shareMap[$data['parent']], (int)$data['permissions'], $userId);
				$shareMap[$data['parent']]->setTarget($data['file_target']);
			}

			$stmt->closeCursor();

			foreach ($shareMap as $share) {
				$result[] = $share;
			}
		}

		return $result;
	}

	/**
	 * Get shares for a given path
	 *
	 * @param Node $path
	 * @return IShare[]
	 */
	public function getSharesByPath(Node $path): array {
		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($path->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_DECK)))
			->execute();

		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * Get shared with the given user
	 *
	 * @param string $userId get shares where this user is the recipient
	 * @param int $shareType
	 * @param Node|null $node
	 * @param int $limit The max number of entries returned, -1 for all
	 * @param int $offset
	 * @return IShare[]
	 */
	public function getSharedWith($userId, $shareType, $node, $limit, $offset): array {
		$allBoards = $this->boardMapper->findBoardIds($userId);

		/** @var IShare[] $shares */
		$shares = [];

		$start = 0;
		while (true) {
			$boards = array_slice($allBoards, $start, 1000);
			$start += 1000;

			if ($boards === []) {
				break;
			}

			$qb = $this->dbConnection->getQueryBuilder();
			$qb->select('s.*',
				'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
				'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
				'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
			)
				->selectAlias('st.id', 'storage_string_id')
				->from('share', 's')
				->orderBy('s.id')
				->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
				->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
				->leftJoin('s', 'deck_cards', 'dc', $qb->expr()->eq($qb->expr()->castColumn('dc.id', IQueryBuilder::PARAM_STR), 's.share_with'))
				->leftJoin('dc', 'deck_stacks', 'ds', $qb->expr()->eq('dc.stack_id', 'ds.id'))
				->leftJoin('ds', 'deck_boards', 'db', $qb->expr()->eq('ds.board_id', 'db.id'));

			if ($limit !== -1) {
				$qb->setMaxResults($limit);
			}

			// Filter by node if provided
			if ($node !== null) {
				$qb->andWhere($qb->expr()->eq('s.file_source', $qb->createNamedParameter($node->getId())));
			}

			$qb->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK)))
				->andWhere($qb->expr()->in('db.id', $qb->createNamedParameter(
					$boards,
					IQueryBuilder::PARAM_STR_ARRAY
				)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('s.item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('s.item_type', $qb->createNamedParameter('folder'))
				));

			$cursor = $qb->execute();
			while ($data = $cursor->fetch()) {
				if (!$this->isAccessibleResult($data)) {
					continue;
				}

				if ($offset > 0) {
					$offset--;
					continue;
				}
				$shares[] = $this->createShareObject($data);
			}
			$cursor->closeCursor();
		}

		$shares = $this->resolveSharesForRecipient($shares, $userId);

		return $shares;
	}

	/**
	 * Get shared with the card
	 *
	 * @param int $cardId
	 * @param int $shareType
	 * @param int $limit The max number of entries returned, -1 for all
	 * @param int $offset
	 * @return IShare[]
	 */
	public function getSharedWithByType(int $cardId, int $shareType, $limit, $offset): array {
		/** @var IShare[] $shares */
		$shares = [];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('s.*',
			'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
			'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
			'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
		)
			->selectAlias('st.id', 'storage_string_id')
			->from('share', 's')
			->orderBy('s.id')
			->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
			->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
			->leftJoin('s', 'deck_cards', 'dc', $qb->expr()->eq($qb->expr()->castColumn('dc.id', IQueryBuilder::PARAM_STR), 's.share_with'));

		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}

		$qb->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK)))
			->andWhere($qb->expr()->eq('s.share_with', $qb->createNamedParameter($cardId)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('folder'))
			));

		$cursor = $qb->executeQuery();
		while ($data = $cursor->fetch()) {
			if (!$this->isAccessibleResult($data)) {
				continue;
			}

			if ($offset > 0) {
				$offset--;
				continue;
			}

			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $this->resolveSharesForRecipient($shares, $this->userId);
	}

	public function isAccessibleResult(array $data): bool {
		// exclude shares leading to deleted file entries
		if ($data['fileid'] === null || $data['path'] === null) {
			return false;
		}

		// exclude shares leading to trashbin on home storages
		$pathSections = explode('/', $data['path'], 2);
		// FIXME: would not detect rare md5'd home storage case properly
		if ($pathSections[0] !== 'files'
			&& in_array(explode(':', $data['storage_string_id'], 2)[0], ['home', 'object'])) {
			return false;
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getShareByToken($token) {
		throw new ShareNotFound();
		/*$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->execute();

		$data = $cursor->fetch();

		if ($data === false) {
			throw new ShareNotFound();
		}

		$roomToken = $data['share_with'];
		try {
			$room = $this->manager->getRoomByToken($roomToken);
		} catch (RoomNotFoundException $e) {
			throw new ShareNotFound();
		}

		if ($room->getType() !== Room::PUBLIC_CALL) {
			throw new ShareNotFound();
		}

		return $this->createShareObject($data);*/
	}

	/**
	 * @inheritDoc
	 */
	public function userDeleted($uid, $shareType) {
		// TODO: Implement userDeleted() method.
	}

	/**
	 * @inheritDoc
	 */
	public function groupDeleted($gid) {
		// TODO: Implement groupDeleted() method.
	}

	/**
	 * @inheritDoc
	 */
	public function userDeletedFromGroup($uid, $gid) {
		// TODO: Implement userDeletedFromGroup() method.
	}

	/**
	 * Get the access list to the array of provided nodes.
	 *
	 * @see IManager::getAccessList() for sample docs
	 *
	 * @param Node[] $nodes The list of nodes to get access for
	 * @param bool $currentAccess If current access is required (like for removed shares that might get revived later)
	 * @return array
	 */
	public function getAccessList($nodes, $currentAccess) {
		$ids = [];
		foreach ($nodes as $node) {
			$ids[] = $node->getId();
		}

		$qb = $this->dbConnection->getQueryBuilder();

		$types = [IShare::TYPE_DECK];
		if ($currentAccess) {
			$types[] = self::SHARE_TYPE_DECK_USER;
		}

		$qb->select('id', 'parent', 'share_type', 'share_with', 'file_source', 'file_target', 'permissions')
			->from('share')
			->where($qb->expr()->in('share_type', $qb->createNamedParameter($types, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->in('file_source', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));
		$cursor = $qb->executeQuery();

		$users = [];
		while ($row = $cursor->fetch()) {
			$type = (int)$row['share_type'];
			if ($type === IShare::TYPE_DECK) {
				$cardId = $row['share_with'];
				$boardId = $this->cardMapper->findBoardId($cardId);
				if ($boardId === null) {
					continue;
				}

				$userList = $this->permissionService->findUsers($boardId);
				/** @var User $user */
				foreach ($userList as $user) {
					$uid = $user->getUID();
					$users[$uid] = $users[$uid] ?? [];
					$users[$uid][$row['id']] = $row;
				}
			} elseif ($type === self::SHARE_TYPE_DECK_USER && $currentAccess === true) {
				$uid = $row['share_with'];
				$users[$uid] = $users[$uid] ?? [];
				$users[$uid][$row['id']] = $row;
			}
		}
		$cursor->closeCursor();

		if ($currentAccess === true) {
			$users = array_map([$this, 'filterSharesOfUser'], $users);
			$users = array_filter($users);
		} else {
			$users = array_keys($users);
		}

		return ['users' => $users];
	}

	/**
	 * For each user the path with the fewest slashes is returned
	 * @param array $shares
	 * @return array
	 */
	protected function filterSharesOfUser(array $shares): array {
		// Deck shares when the user has a share exception
		foreach ($shares as $id => $share) {
			$type = (int) $share['share_type'];
			$permissions = (int) $share['permissions'];

			if ($type === self::SHARE_TYPE_DECK_USER) {
				unset($shares[$share['parent']]);

				if ($permissions === 0) {
					unset($shares[$id]);
				}
			}
		}

		$best = [];
		$bestDepth = 0;
		foreach ($shares as $id => $share) {
			$depth = substr_count($share['file_target'], '/');
			if (empty($best) || $depth < $bestDepth) {
				$bestDepth = $depth;
				$best = [
					'node_id' => $share['file_source'],
					'node_path' => $share['file_target'],
				];
			}
		}

		return $best;
	}

	/**
	 * Get all children of this share
	 *
	 * Not part of IShareProvider API, but needed by OC\Share20\Manager.
	 *
	 * @param IShare $parent
	 * @return IShare[]
	 */
	public function getChildren(IShare $parent): array {
		$children = [];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($parent->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_DECK)))
			->orderBy('id');

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$children[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $children;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllShares(): iterable {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_DECK))
				)
			);

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$share = $this->createShareObject($data);

			yield $share;
		}
		$cursor->closeCursor();
	}
}
