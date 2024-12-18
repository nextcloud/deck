<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCA\Deck\Service\CirclesService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\Cache\CappedMemoryCache;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<Board> */
class BoardMapper extends QBMapper implements IPermissionMapper {
	/** @var CappedMemoryCache<Board[]> */
	private CappedMemoryCache $userBoardCache;
	/** @var CappedMemoryCache<Board> */
	private CappedMemoryCache $boardCache;

	public function __construct(
		IDBConnection $db,
		private LabelMapper $labelMapper,
		private AclMapper $aclMapper,
		private StackMapper $stackMapper,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private CirclesService $circlesService,
		private LoggerInterface $logger,
	) {
		parent::__construct($db, 'deck_boards', Board::class);

		$this->userBoardCache = new CappedMemoryCache();
		$this->boardCache = new CappedMemoryCache();
	}


	/**
	 * @param $id
	 * @param bool $withLabels
	 * @param bool $withAcl
	 * @return Board
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id, bool $withLabels = false, bool $withAcl = false, bool $allowDeleted = false): Board {
		$cacheKey = (string)$id;
		if (!isset($this->boardCache[$cacheKey])) {
			$qb = $this->db->getQueryBuilder();
			$deletedWhere = $allowDeleted ? $qb->expr()->gte('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)) : $qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT));
			$qb->select('*')
				->from('deck_boards')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
				->andWhere($deletedWhere)
				->orderBy('id');
			$this->boardCache[(string)$id] = $this->findEntity($qb);
		}

		// Add labels
		if ($withLabels && $this->boardCache[$cacheKey]->getLabels() === null) {
			$labels = $this->labelMapper->findAll($id);
			$this->boardCache[$cacheKey]->setLabels($labels);
		}

		// Add acl
		if ($withAcl && $this->boardCache[$cacheKey]->getAcl() === null) {
			$acl = $this->aclMapper->findAll($id);
			$this->boardCache[$cacheKey]->setAcl($acl);
		}

		return $this->boardCache[$cacheKey];
	}

	public function findBoardIds(string $userId): array {
		// Owned by the user
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('b.id')
			->from($this->getTableName(), 'b')
			->where($qb->expr()->andX(
				$qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
			));
		$result = $qb->executeQuery();
		$ownerBoards = array_map(function (string $id) {
			return (int)$id;
		}, $result->fetchAll(\PDO::FETCH_COLUMN));
		$result->closeCursor();

		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('b.id')
			->from($this->getTableName(), 'b')
			->innerJoin('b', 'deck_board_acl', 'acl', $qb->expr()->eq('b.id', 'acl.board_id'));

		// Shared to the user
		$qb->where($qb->expr()->andX(
			$qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_USER, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('acl.participant', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
		));

		// Shared to user groups of the user
		$groupIds = $this->groupManager->getUserGroupIds($this->userManager->get($userId));
		if (count($groupIds) !== 0) {
			$qb->orWhere($qb->expr()->andX(
				$qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_GROUP, IQueryBuilder::PARAM_INT)),
				$qb->expr()->in('acl.participant', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY)),
			));
		}

		// Shared to circles of the user
		$circles = $this->circlesService->getUserCircles($userId);
		if (count($circles) !== 0) {
			$qb->orWhere($qb->expr()->andX(
				$qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_CIRCLE, IQueryBuilder::PARAM_INT)),
				$qb->expr()->in('acl.participant', $qb->createNamedParameter($circles, IQueryBuilder::PARAM_STR_ARRAY)),
			));
		}

		$result = $qb->executeQuery();
		$sharedBoards = array_map(function (string $id) {
			return (int)$id;
		}, $result->fetchAll(\PDO::FETCH_COLUMN));
		$result->closeCursor();
		return array_unique(array_merge($ownerBoards, $sharedBoards));
	}

	public function findAllForUser(string $userId, ?int $since = null, bool $includeArchived = true, ?int $before = null,
		?string $term = null): array {
		$useCache = ($since === -1 && $includeArchived === true && $before === null && $term === null);
		if (!isset($this->userBoardCache[$userId]) || !$useCache) {
			$groups = $this->groupManager->getUserGroupIds(
				$this->userManager->get($userId)
			);
			$userBoards = $this->findAllByUser($userId, null, null, $since, $includeArchived, $before, $term);
			$groupBoards = $this->findAllByGroups($userId, $groups, null, null, $since, $includeArchived, $before, $term);
			$circleBoards = $this->findAllByCircles($userId, null, null, $since, $includeArchived, $before, $term);
			$allBoards = array_values(array_unique(array_merge($userBoards, $groupBoards, $circleBoards)));

			// Could be moved outside
			$acls = $this->aclMapper->findIn(array_map(function ($board) {
				return $board->getId();
			}, $allBoards));

			/* @var Board $entry */
			foreach ($allBoards as $entry) {
				$boardAcls = array_values(array_filter($acls, function ($acl) use ($entry) {
					return $acl->getBoardId() === $entry->getId();
				}));
				$entry->setAcl($boardAcls);
			}

			foreach ($allBoards as $board) {
				$this->boardCache[$board->getId()] = $board;
			}
			if ($useCache) {
				$this->userBoardCache[$userId] = $allBoards;
			}
			return $allBoards;
		}
		return $this->userBoardCache[$userId];
	}

	/**
	 * Find all boards for a given user
	 */
	public function findAllByUser(string $userId, ?int $limit = null, ?int $offset = null, ?int $since = null,
		bool $includeArchived = true, ?int $before = null, ?string $term = null): array {
		// FIXME this used to be a UNION to get boards owned by $userId and the user shares in one single query
		// Is it possible with the query builder?
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'title', 'owner', 'color', 'archived', 'deleted_at', 'last_modified')
			// this does not work in MySQL/PostgreSQL
			//->selectAlias('0', 'shared')
			->from('deck_boards', 'b')
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		if (!$includeArchived) {
			$qb->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
				->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		}
		if ($since !== null) {
			$qb->andWhere($qb->expr()->gt('last_modified', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));
		}
		if ($before !== null) {
			$qb->andWhere($qb->expr()->lt('last_modified', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		}
		if ($term !== null) {
			$qb->andWhere(
				$qb->expr()->iLike(
					'title',
					$qb->createNamedParameter(
						'%' . $this->db->escapeLikeParameter($term) . '%',
						IQueryBuilder::PARAM_STR
					)
				)
			);
		}
		$qb->orderBy('b.id');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}
		$entries = $this->findEntities($qb);
		foreach ($entries as $entry) {
			$entry->setShared(0);
		}

		// shared with user
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'title', 'owner', 'color', 'archived', 'deleted_at', 'last_modified')
			//->selectAlias('1', 'shared')
			->from('deck_boards', 'b')
			->innerJoin('b', 'deck_board_acl', 'acl', $qb->expr()->eq('b.id', 'acl.board_id'))
			->where($qb->expr()->eq('acl.participant', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_USER, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('b.owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		if (!$includeArchived) {
			$qb->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
				->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		}
		if ($since !== null) {
			$qb->andWhere($qb->expr()->gt('last_modified', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));
		}
		if ($before !== null) {
			$qb->andWhere($qb->expr()->lt('last_modified', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		}
		if ($term !== null) {
			$qb->andWhere(
				$qb->expr()->iLike(
					'title',
					$qb->createNamedParameter(
						'%' . $this->db->escapeLikeParameter($term) . '%',
						IQueryBuilder::PARAM_STR
					)
				)
			);
		}
		$qb->orderBy('b.id');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}
		$sharedEntries = $this->findEntities($qb);
		foreach ($sharedEntries as $entry) {
			$entry->setShared(1);
		}
		$entries = array_merge($entries, $sharedEntries);

		return $entries;
	}

	public function findAllByOwner(string $userId, ?int $limit = null, ?int $offset = null) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_boards')
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('id');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}
		return $this->findEntities($qb);
	}

	/**
	 * Find all boards for a given user
	 */
	public function findAllByGroups(string $userId, array $groups, ?int $limit = null, ?int $offset = null, ?int $since = null,
		bool $includeArchived = true, ?int $before = null, ?string $term = null): array {
		if (count($groups) <= 0) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'title', 'owner', 'color', 'archived', 'deleted_at', 'last_modified')
			//->selectAlias('2', 'shared')
			->from('deck_boards', 'b')
			->innerJoin('b', 'deck_board_acl', 'acl', $qb->expr()->eq('b.id', 'acl.board_id'))
			->where($qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_GROUP, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('b.owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		$or = $qb->expr()->orx();
		for ($i = 0, $iMax = count($groups); $i < $iMax; $i++) {
			$or->add(
				$qb->expr()->eq('acl.participant', $qb->createNamedParameter($groups[$i], IQueryBuilder::PARAM_STR))
			);
		}
		$qb->andWhere($or);
		if (!$includeArchived) {
			$qb->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
				->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		}
		if ($since !== null) {
			$qb->andWhere($qb->expr()->gt('last_modified', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));
		}
		if ($before !== null) {
			$qb->andWhere($qb->expr()->lt('last_modified', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		}
		if ($term !== null) {
			$qb->andWhere(
				$qb->expr()->iLike(
					'title',
					$qb->createNamedParameter(
						'%' . $this->db->escapeLikeParameter($term) . '%',
						IQueryBuilder::PARAM_STR
					)
				)
			);
		}
		$qb->orderBy('b.id');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}
		$entries = $this->findEntities($qb);
		foreach ($entries as $entry) {
			$entry->setShared(2);
		}
		return $entries;
	}

	public function findAllByCircles(string $userId, ?int $limit = null, ?int $offset = null, ?int $since = null,
		bool $includeArchived = true, ?int $before = null, ?string $term = null) {
		$circles = $this->circlesService->getUserCircles($userId);
		if (count($circles) === 0) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'title', 'owner', 'color', 'archived', 'deleted_at', 'last_modified')
			//->selectAlias('2', 'shared')
			->from('deck_boards', 'b')
			->innerJoin('b', 'deck_board_acl', 'acl', $qb->expr()->eq('b.id', 'acl.board_id'))
			->where($qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_CIRCLE, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('b.owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		$or = $qb->expr()->orx();
		for ($i = 0, $iMax = count($circles); $i < $iMax; $i++) {
			$or->add(
				$qb->expr()->eq('acl.participant', $qb->createNamedParameter($circles[$i], IQueryBuilder::PARAM_STR))
			);
		}
		$qb->andWhere($or);
		if (!$includeArchived) {
			$qb->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
				->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		}
		if ($since !== null) {
			$qb->andWhere($qb->expr()->gt('last_modified', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));
		}
		if ($before !== null) {
			$qb->andWhere($qb->expr()->lt('last_modified', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		}
		if ($term !== null) {
			$qb->andWhere(
				$qb->expr()->iLike(
					'title',
					$qb->createNamedParameter(
						'%' . $this->db->escapeLikeParameter($term) . '%',
						IQueryBuilder::PARAM_STR
					)
				)
			);
		}
		$qb->orderBy('b.id');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}
		$entries = $this->findEntities($qb);
		foreach ($entries as $entry) {
			$entry->setShared(2);
		}
		return $entries;
	}

	public function findAllByTeam(string $teamId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'title', 'owner', 'color', 'archived', 'deleted_at', 'last_modified')
			->from('deck_boards', 'b')
			->innerJoin('b', 'deck_board_acl', 'acl', $qb->expr()->eq('b.id', 'acl.board_id'))
			->where($qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_CIRCLE, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('acl.participant', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_STR)));
		$entries = $this->findEntities($qb);
		foreach ($entries as $entry) {
			$entry->setShared(2);
		}
		return $entries;
	}

	public function findTeamsForBoard(int $boardId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('acl.participant')
			->from('deck_boards', 'b')
			->innerJoin('b', 'deck_board_acl', 'acl', $qb->expr()->eq('b.id', 'acl.board_id'))
			->where($qb->expr()->eq('b.id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_CIRCLE, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		return array_map(function ($entry) {
			return $entry['participant'];
		}, $result->fetchAll());
	}

	public function isSharedWithTeam(int $boardId, string $teamId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'title', 'owner', 'color', 'archived', 'deleted_at', 'last_modified')
			->from('deck_boards', 'b')
			->innerJoin('b', 'deck_board_acl', 'acl', $qb->expr()->eq('b.id', 'acl.board_id'))
			->where($qb->expr()->eq('b.id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_CIRCLE, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('acl.participant', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_STR)));
		try {
			$this->findEntity($qb);
			return true;
		} catch (DoesNotExistException $e) {
			// Expected return falue
		}
		return false;
	}

	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('deck_boards');
		return $this->findEntities($qb);
	}

	public function findToDelete() {
		// add buffer of 5 min
		$timeLimit = time() - (60 * 5);
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'title', 'owner', 'color', 'archived', 'deleted_at', 'last_modified')
			->from('deck_boards')
			->where($qb->expr()->gt('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('deleted_at', $qb->createNamedParameter($timeLimit, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function delete(/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
		\OCP\AppFramework\Db\Entity $entity): \OCP\AppFramework\Db\Entity {
		// delete acl
		$acl = $this->aclMapper->findAll($entity->getId());
		foreach ($acl as $item) {
			$this->aclMapper->delete($item);
		}

		// delete stacks ( includes cards, assigned labels)
		$stacks = $this->stackMapper->findAll($entity->getId());
		foreach ($stacks as $stack) {
			$this->stackMapper->delete($stack);
		}
		// delete labels
		$labels = $this->labelMapper->findAll($entity->getId());
		foreach ($labels as $label) {
			$this->labelMapper->delete($label);
		}

		return parent::delete($entity);
	}

	public function isOwner($userId, $id): bool {
		$board = $this->find($id);
		return ($board->getOwner() === $userId);
	}

	public function findBoardId($id): ?int {
		return $id;
	}

	public function mapAcl(Acl &$acl) {
		$acl->resolveRelation('participant', function ($participant) use (&$acl) {
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
				if ($this->userManager->userExists($acl->getParticipant())) {
					return new User($acl->getParticipant(), $this->userManager);
				}
				$this->logger->debug('User ' . $acl->getId() . ' not found when mapping acl ' . $acl->getParticipant());
				return null;
			}
			if ($acl->getType() === Acl::PERMISSION_TYPE_GROUP) {
				$group = $this->groupManager->get($participant);
				if ($group !== null) {
					return new Group($group);
				}
				$this->logger->debug('Group ' . $acl->getId() . ' not found when mapping acl ' . $acl->getParticipant());
				return null;
			}
			if ($acl->getType() === Acl::PERMISSION_TYPE_CIRCLE) {
				if (!$this->circlesService->isCirclesEnabled()) {
					return null;
				}
				try {
					$circle = $this->circlesService->getCircle($acl->getParticipant());
					if ($circle) {
						return new Circle($circle);
					}
				} catch (\Throwable $e) {
					$this->logger->error('Failed to get circle details when building ACL', ['exception' => $e]);
				}
				return null;
			}
			$this->logger->warning('Unknown permission type for mapping acl ' . $acl->getId());
			return null;
		});
	}

	/**
	 * @param Board $board
	 */
	public function mapOwner(Board &$board) {
		$userManager = $this->userManager;
		$board->resolveRelation('owner', function ($owner) use (&$userManager) {
			if ($this->userManager->userExists($owner)) {
				return new User($owner, $userManager);
			}
			return null;
		});
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function transferOwnership(string $ownerId, string $newOwnerId, $boardId = null): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('deck_boards')
			->set('owner', $qb->createNamedParameter($newOwnerId, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($ownerId, IQueryBuilder::PARAM_STR)));
		if ($boardId !== null) {
			$qb->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
		}
		$qb->executeStatement();
	}

	/**
	 * Reset cache for a given board or a given user
	 */
	public function flushCache(?int $boardId = null, ?string $userId = null) {
		if ($boardId) {
			unset($this->boardCache[$boardId]);
		} else {
			$this->boardCache = new CappedMemoryCache();
		}
		if ($userId) {
			unset($this->userBoardCache[$userId]);
		} else {
			$this->userBoardCache = new CappedMemoryCache();
		}
	}
}
