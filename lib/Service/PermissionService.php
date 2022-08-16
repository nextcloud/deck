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

namespace OCA\Deck\Service;

use OCP\Cache\CappedMemoryCache;
use OCA\Circles\Model\Member;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\IPermissionMapper;
use OCA\Deck\Db\User;
use OCA\Deck\NoPermissionException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Share\IManager;

class PermissionService {

	/** @var CirclesService */
	private $circlesService;
	/** @var BoardMapper */
	private $boardMapper;
	/** @var AclMapper */
	private $aclMapper;
	/** @var ILogger */
	private $logger;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IConfig */
	private $config;
	/** @var IManager */
	private $shareManager;
	/** @var string */
	private $userId;
	/** @var array */
	private $users = [];

	private CappedMemoryCache $boardCache;
	private CappedMemoryCache $permissionCache;

	public function __construct(
		ILogger $logger,
		CirclesService $circlesService,
		AclMapper $aclMapper,
		BoardMapper $boardMapper,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IManager $shareManager,
		IConfig $config,
		$userId
	) {
		$this->circlesService = $circlesService;
		$this->aclMapper = $aclMapper;
		$this->boardMapper = $boardMapper;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->shareManager = $shareManager;
		$this->config = $config;
		$this->userId = $userId;

		$this->boardCache = new CappedMemoryCache();
		$this->permissionCache = new CappedMemoryCache();
	}

	/**
	 * Get current user permissions for a board by id
	 *
	 * @param $boardId
	 * @return bool|array
	 */
	public function getPermissions($boardId) {
		if ($cached = $this->permissionCache->get($boardId)) {
			return $cached;
		}

		$owner = $this->userIsBoardOwner($boardId);
		$acls = $this->aclMapper->findAll($boardId);
		$permissions = [
			Acl::PERMISSION_READ => $owner || $this->userCan($acls, Acl::PERMISSION_READ),
			Acl::PERMISSION_EDIT => $owner || $this->userCan($acls, Acl::PERMISSION_EDIT),
			Acl::PERMISSION_MANAGE => $owner || $this->userCan($acls, Acl::PERMISSION_MANAGE),
			Acl::PERMISSION_SHARE => ($owner || $this->userCan($acls, Acl::PERMISSION_SHARE))
				&& (!$this->shareManager->sharingDisabledForUser($this->userId))
		];
		$this->permissionCache->set($boardId, $permissions);
		return $permissions;
	}

	/**
	 * Get current user permissions for a board
	 *
	 * @param Board $board
	 * @return array|bool
	 * @internal param $boardId
	 */
	public function matchPermissions(Board $board) {
		$owner = $this->userIsBoardOwner($board->getId());
		$acls = $board->getAcl() ?? [];
		return [
			Acl::PERMISSION_READ => $owner || $this->userCan($acls, Acl::PERMISSION_READ),
			Acl::PERMISSION_EDIT => $owner || $this->userCan($acls, Acl::PERMISSION_EDIT),
			Acl::PERMISSION_MANAGE => $owner || $this->userCan($acls, Acl::PERMISSION_MANAGE),
			Acl::PERMISSION_SHARE => ($owner || $this->userCan($acls, Acl::PERMISSION_SHARE))
				&& (!$this->shareManager->sharingDisabledForUser($this->userId))
		];
	}

	/**
	 * check permissions for replacing dark magic middleware
	 *
	 * @param $mapper IPermissionMapper|null null if $id is a boardId
	 * @param $id int unique identifier of the Entity
	 * @param $permission int
	 * @return bool
	 * @throws NoPermissionException
	 */
	public function checkPermission($mapper, $id, $permission, $userId = null) {
		$boardId = $id;
		if ($mapper instanceof IPermissionMapper && !($mapper instanceof BoardMapper)) {
			$boardId = $mapper->findBoardId($id);
		}
		if ($boardId === null) {
			// Throw NoPermission to not leak information about existing entries
			throw new NoPermissionException('Permission denied');
		}

		if ($permission === Acl::PERMISSION_SHARE && $this->shareManager->sharingDisabledForUser($this->userId)) {
			throw new NoPermissionException('Permission denied');
		}

		if ($this->userIsBoardOwner($boardId, $userId)) {
			return true;
		}

		try {
			$acls = $this->getBoard($boardId)->getAcl() ?? [];
			$result = $this->userCan($acls, $permission, $userId);
			if ($result) {
				return true;
			}
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
		}

		// Throw NoPermission to not leak information about existing entries
		throw new NoPermissionException('Permission denied');
	}

	/**
	 * @param $boardId
	 * @return bool
	 */
	public function userIsBoardOwner($boardId, $userId = null) {
		if ($userId === null) {
			$userId = $this->userId;
		}
		try {
			$board = $this->getBoard($boardId);
			return $userId === $board->getOwner();
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
		}
		return false;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	private function getBoard($boardId): Board {
		if (!isset($this->boardCache[$boardId])) {
			$this->boardCache[$boardId] = $this->boardMapper->find($boardId, false, true);
		}
		return $this->boardCache[$boardId];
	}

	/**
	 * Check if permission matches the acl rules for current user and groups
	 *
	 * @param Acl[] $acls
	 * @param $permission
	 * @return bool
	 */
	public function userCan(array $acls, $permission, $userId = null) {
		if ($userId === null) {
			$userId = $this->userId;
		}
		// check for users
		foreach ($acls as $acl) {
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER && $acl->getParticipant() === $userId) {
				return $acl->getPermission($permission);
			}

			if ($this->circlesService->isCirclesEnabled() && $acl->getType() === Acl::PERMISSION_TYPE_CIRCLE) {
				try {
					if ($this->circlesService->isUserInCircle($acl->getParticipant(), $userId) && $acl->getPermission($permission)) {
						return true;
					}
				} catch (\Exception $e) {
					$this->logger->info('Member not found in circle that was accessed. This should not happen.');
				}
			}
		}
		// check for groups
		$hasGroupPermission = false;
		foreach ($acls as $acl) {
			if (!$hasGroupPermission && $acl->getType() === Acl::PERMISSION_TYPE_GROUP && $this->groupManager->isInGroup($userId, $acl->getParticipant())) {
				$hasGroupPermission = $acl->getPermission($permission);
			}
		}
		return $hasGroupPermission;
	}

	/**
	 * Find a list of all users (including the ones from groups)
	 * Required to allow assigning them to cards
	 *
	 * @param $boardId
	 * @return array
	 */
	public function findUsers($boardId, $refresh = false) {
		// cache users of a board so we don't query them for every cards
		if (array_key_exists((string) $boardId, $this->users) && !$refresh) {
			return $this->users[(string) $boardId];
		}

		try {
			$board = $this->boardMapper->find($boardId);
		} catch (DoesNotExistException $e) {
			return [];
		} catch (MultipleObjectsReturnedException $e) {
			return [];
		}

		$users = [];
		$owner = $this->userManager->get($board->getOwner());
		if ($owner === null) {
			$this->logger->info('No owner found for board ' . $board->getId());
		} else {
			$users[$owner->getUID()] = new User($owner);
		}
		$acls = $this->aclMapper->findAll($boardId);
		/** @var Acl $acl */
		foreach ($acls as $acl) {
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
				$user = $this->userManager->get($acl->getParticipant());
				if ($user === null) {
					$this->logger->info('No user found for acl rule ' . $acl->getId());
					continue;
				}
				$users[$user->getUID()] = new User($user);
			}
			if ($acl->getType() === Acl::PERMISSION_TYPE_GROUP) {
				$group = $this->groupManager->get($acl->getParticipant());
				if ($group === null) {
					$this->logger->info('No group found for acl rule ' . $acl->getId());
					continue;
				}
				foreach ($group->getUsers() as $user) {
					$users[$user->getUID()] = new User($user);
				}
			}

			if ($this->circlesService->isCirclesEnabled() && $acl->getType() === Acl::PERMISSION_TYPE_CIRCLE) {
				try {
					$circle = $this->circlesService->getCircle($acl->getParticipant());
					if ($circle === null) {
						$this->logger->info('No circle found for acl rule ' . $acl->getId());
						continue;
					}

					foreach ($circle->getInheritedMembers() as $member) {
						if ($member->getUserType() !== 1 || $member->getLevel() < Member::LEVEL_MEMBER) {
							// deck currently only supports user members in circles
							continue;
						}
						$user = $this->userManager->get($member->getUserId());
						if ($user === null) {
							$this->logger->info('No user found for circle member ' . $member->getUserId());
						} else {
							$users[$member->getUserId()] = new User($user);
						}
					}
				} catch (\Exception $e) {
					$this->logger->info('Member not found in circle that was accessed. This should not happen.');
				}
			}
		}
		$this->users[(string) $boardId] = $users;
		return $this->users[(string) $boardId];
	}

	public function canCreate() {
		if ($this->userId === null) {
			return false;
		}

		$groups = $this->getGroupLimitList();
		if (count($groups) === 0) {
			return true;
		}
		foreach ($groups as $group) {
			if ($this->groupManager->isInGroup($this->userId, $group)) {
				return true;
			}
		}
		return false;
	}

	private function getGroupLimitList() {
		$value = $this->config->getAppValue('deck', 'groupLimit', '');
		$groups = explode(',', $value);
		if ($value === '') {
			return [];
		}
		return $groups;
	}

	/**
	 * Set a different user than the current one, e.g. when no user is available in occ
	 *
	 * @param string $userId
	 */
	public function setUserId(string $userId): void {
		$this->userId = $userId;
		$this->permissionCache->clear();
	}
}
