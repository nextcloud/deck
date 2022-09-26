<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Db;

use OC\Cache\CappedMemoryCache;
use OCA\Deck\Service\CirclesService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class BoardMapper extends QBMapper implements IPermissionMapper {
	private $labelMapper;
	private $aclMapper;
	private $stackMapper;
	private $userManager;
	private $groupManager;
	private $circlesService;
	private $logger;

	/** @var CappedMemoryCache<Board[]> */
	private $userBoardCache;
	/** @var CappedMemoryCache<Board> */
	private $boardCache;

	public function __construct(
		IDBConnection $db,
		LabelMapper $labelMapper,
		AclMapper $aclMapper,
		StackMapper $stackMapper,
		IUserManager $userManager,
		IGroupManager $groupManager,
		CirclesService $circlesService,
		LoggerInterface $logger
	) {
		parent::__construct($db, 'deck_boards', Board::class);
		$this->labelMapper = $labelMapper;
		$this->aclMapper = $aclMapper;
		$this->stackMapper = $stackMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->circlesService = $circlesService;
		$this->logger = $logger;

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
	public function find($id, $withLabels = false, $withAcl = false): Board {
		if (!isset($this->boardCache[$id])) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from('deck_boards')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
				->orderBy('id');
			$this->boardCache[$id] = $this->findEntity($qb);
		}

		// FIXME is this necessary? it was NOT done with the old mapper
		// $this->mapOwner($board);

		// Add labels
		if ($withLabels && $this->boardCache[$id]->getLabels() === null) {
			$labels = $this->labelMapper->findAll($id);
			$this->boardCache[$id]->setLabels($labels);
		}

		// Add acl
		if ($withAcl && $this->boardCache[$id]->getAcl() === null) {
			$acl = $this->aclMapper->findAll($id);
			$this->boardCache[$id]->setAcl($acl);
		}

		return $this->boardCache[$id];
	}

	public function findBoardIds(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('b.id')
			->from($this->getTableName(), 'b')
			->leftJoin('b', 'deck_board_acl', 'acl', $qb->expr()->eq('b.id', 'acl.board_id'));

		// Owned by the user
		$qb->where($qb->expr()->andX(
			$qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
		));

		// Shared to the user
		$qb->orWhere($qb->expr()->andX(
			$qb->expr()->eq('acl.participant', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
			$qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_USER, IQueryBuilder::PARAM_INT)),
		));

		// Shared to user groups of the user
		$groupIds = $this->groupManager->getUserGroupIds($this->userManager->get($userId));
		if (count($groupIds) !== 0) {
			$qb->orWhere($qb->expr()->andX(
					$qb->expr()->in('acl.participant', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY)),
					$qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_GROUP, IQueryBuilder::PARAM_INT)),
				));
		}

		// Shared to circles of the user
		$circles = $this->circlesService->getUserCircles($userId);
		if (count($circles) !== 0) {
			$qb->orWhere($qb->expr()->andX(
				$qb->expr()->in('acl.participant', $qb->createNamedParameter($circles, IQueryBuilder::PARAM_STR_ARRAY)),
				$qb->expr()->eq('acl.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_CIRCLE, IQueryBuilder::PARAM_INT)),
			));
		}

		$result = $qb->executeQuery();
		return array_map(function (string $id) {
			return (int)$id;
		}, $result->fetchAll(\PDO::FETCH_COLUMN));
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
			$allBoards = array_unique(array_merge($userBoards, $groupBoards, $circleBoards));
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
		$qb->resetQueryParts();
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
		/* @var Board $entry */
		foreach ($entries as $entry) {
			$acl = $this->aclMapper->findAll($entry->id);
			$entry->setAcl($acl);
		}
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
		/* @var Board $entry */
		foreach ($entries as $entry) {
			$acl = $this->aclMapper->findAll($entry->id);
			$entry->setAcl($acl);
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
		/* @var Board $entry */
		foreach ($entries as $entry) {
			$acl = $this->aclMapper->findAll($entry->id);
			$entry->setAcl($acl);
		}
		return $entries;
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
		$userManager = $this->userManager;
		$groupManager = $this->groupManager;
		$acl->resolveRelation('participant', function ($participant) use (&$acl, &$userManager, &$groupManager) {
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
				$user = $userManager->get($participant);
				if ($user !== null) {
					return new User($user);
				}
				$this->logger->debug('User ' . $acl->getId() . ' not found when mapping acl ' . $acl->getParticipant());
				return null;
			}
			if ($acl->getType() === Acl::PERMISSION_TYPE_GROUP) {
				$group = $groupManager->get($participant);
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
			$user = $userManager->get($owner);
			if ($user !== null) {
				return new User($user);
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
			$this->boardCache = null;
		}
		if ($userId) {
			unset($this->userBoardCache[$userId]);
		} else {
			$this->userBoardCache = null;
		}
	}
}
