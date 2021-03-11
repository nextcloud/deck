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
	private $logger;

	private $circlesEnabled;

	/** @var CappedMemoryCache */
	private $userBoardCache;
	/** @var CappedMemoryCache */
	private $boardCache;

	public function __construct(
		IDBConnection $db,
		LabelMapper $labelMapper,
		AclMapper $aclMapper,
		StackMapper $stackMapper,
		IUserManager $userManager,
		IGroupManager $groupManager,
		LoggerInterface $logger
	) {
		parent::__construct($db, 'deck_boards', Board::class);
		$this->labelMapper = $labelMapper;
		$this->aclMapper = $aclMapper;
		$this->stackMapper = $stackMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->logger = $logger;

		$this->userBoardCache = new CappedMemoryCache();
		$this->boardCache = new CappedMemoryCache();

		$this->circlesEnabled = \OC::$server->getAppManager()->isEnabledForUser('circles');
	}


	/**
	 * @param $id
	 * @param bool $withLabels
	 * @param bool $withAcl
	 * @return Board
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find($id, $withLabels = false, $withAcl = false) {
		$sql = 'SELECT id, title, owner, color, archived, deleted_at, last_modified, cover_images FROM `*PREFIX*deck_boards` ' .
			'WHERE `id` = ?';
		$board = $this->findEntity($sql, [$id]);

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
	 *
	 * @param $userId
	 * @param null $limit
	 * @param null $offset
	 * @return array
	 */
	public function findAllByUser($userId, $limit = null, $offset = null, $since = -1, $includeArchived = true) {
		// FIXME: One moving to QBMapper we should allow filtering the boards probably by method chaining for additional where clauses
		$sql = 'SELECT id, title, owner, color, archived, deleted_at, 0 as shared, last_modified, cover_images FROM `*PREFIX*deck_boards` WHERE owner = ? AND last_modified > ?';
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
		$sql .= ' UNION ' .
			'SELECT boards.id, title, owner, color, archived, deleted_at, 1 as shared, last_modified, cover_images FROM `*PREFIX*deck_boards` as boards ' .
			'JOIN `*PREFIX*deck_board_acl` as acl ON boards.id=acl.board_id WHERE acl.participant=? AND acl.type=? AND boards.owner != ? AND last_modified > ?';
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
	 *
	 * @param $userId
	 * @param $groups
	 * @param null $limit
	 * @param null $offset
	 * @return array
	 */
	public function findAllByGroups(string $userId, array $groups, ?int $limit = null, ?int $offset = null, ?int $since = null,
									bool $includeArchived = true, ?int $before = null, ?string $term = null) {
		if (count($groups) <= 0) {
			return [];
		}
		$sql = 'SELECT boards.id, title, owner, color, archived, deleted_at, 2 as shared, last_modified, cover_images FROM `*PREFIX*deck_boards` as boards ' .
			'INNER JOIN `*PREFIX*deck_board_acl` as acl ON boards.id=acl.board_id WHERE owner != ? AND type=? AND (';
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
		if (!$this->circlesEnabled) {
			return [];
		}
		$circles = array_map(function ($circle) {
			return $circle->getUniqueId();
		}, \OCA\Circles\Api\v1\Circles::joinedCircles($userId, true));
		if (count($circles) === 0) {
			return [];
		}

		$sql = 'SELECT boards.id, title, owner, color, archived, deleted_at, 2 as shared, last_modified, cover_images FROM `*PREFIX*deck_boards` as boards ' .
			'INNER JOIN `*PREFIX*deck_board_acl` as acl ON boards.id=acl.board_id WHERE owner != ? AND type=? AND (';
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
		$sql = 'SELECT id, title, owner, color, archived, deleted_at, last_modified, cover_images FROM `*PREFIX*deck_boards` ' .
			'WHERE `deleted_at` > 0 AND `deleted_at` < ?';
		return $this->findEntities($sql, [$timeLimit]);
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

	public function isOwner($userId, $boardId): bool {
		$board = $this->find($boardId);
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
				if (!$this->circlesEnabled) {
					return null;
				}
				try {
					$circle = \OCA\Circles\Api\v1\Circles::detailsCircle($acl->getParticipant(), true);
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
}
