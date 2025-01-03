<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Circles\Model\Member;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\IPermissionMapper;
use OCA\Deck\Db\User;
use OCA\Deck\NoPermissionException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Cache\CappedMemoryCache;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

class PermissionService {
	private array $users = [];

	private CappedMemoryCache $boardCache;
	private CappedMemoryCache $permissionCache;

	public function __construct(
		private LoggerInterface $logger,
		private CirclesService $circlesService,
		private AclMapper $aclMapper,
		private BoardMapper $boardMapper,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IManager $shareManager,
		private IConfig $config,
		private ?string $userId,
	) {
		$this->boardCache = new CappedMemoryCache();
		$this->permissionCache = new CappedMemoryCache();
	}

	/**
	 * Get current user permissions for a board by id
	 *
	 * @return bool|array
	 */
	public function getPermissions(int $boardId, ?string $userId = null) {
		if ($userId === null) {
			$userId = $this->userId;
		}

		$cacheKey = $boardId . '-' . $userId;
		if ($cached = $this->permissionCache->get($cacheKey)) {
			return $cached;
		}

		try {
			$board = $this->getBoard($boardId);
			$owner = $this->userIsBoardOwner($boardId, $userId);
			$acls = $board->getDeletedAt() === 0 ? $this->aclMapper->findAll($boardId) : [];
		} catch (MultipleObjectsReturnedException|DoesNotExistException $e) {
			$owner = false;
			$acls = [];
		}

		$permissions = [
			Acl::PERMISSION_READ => $owner || $this->userCan($acls, Acl::PERMISSION_READ, $userId),
			Acl::PERMISSION_EDIT => $owner || $this->userCan($acls, Acl::PERMISSION_EDIT, $userId),
			Acl::PERMISSION_MANAGE => $owner || $this->userCan($acls, Acl::PERMISSION_MANAGE, $userId),
			Acl::PERMISSION_SHARE => ($owner || $this->userCan($acls, Acl::PERMISSION_SHARE, $userId))
				&& (!$this->shareManager->sharingDisabledForUser($userId))
		];
		$this->permissionCache->set($cacheKey, $permissions);
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
	 * @throws NoPermissionException
	 */
	public function checkPermission(?IPermissionMapper $mapper, $id, int $permission, $userId = null, bool $allowDeletedCard = false): bool {
		$boardId = (int)$id;
		if ($mapper instanceof IPermissionMapper && !($mapper instanceof BoardMapper)) {
			$boardId = $mapper->findBoardId($id);
		}
		if ($boardId === null) {
			// Throw NoPermission to not leak information about existing entries
			throw new NoPermissionException('Permission denied');
		}

		$permissions = $this->getPermissions($boardId, $userId);
		if ($permissions[$permission] === true) {

			if (!$allowDeletedCard && $mapper instanceof CardMapper) {
				try {
					$card = $mapper->find((int)$id, false);
				} catch (DoesNotExistException $e) {
					throw new NoPermissionException('Permission denied');
				}
				if ($card->getDeletedAt() > 0) {
					throw new NoPermissionException('Card is deleted');
				}
			}

			return true;
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
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
		}
		return false;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	private function getBoard(int $boardId): Board {
		if (!isset($this->boardCache[(string)$boardId])) {
			$this->boardCache[(string)$boardId] = $this->boardMapper->find($boardId, false, true);
		}
		return $this->boardCache[(string)$boardId];
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
		if (array_key_exists((string)$boardId, $this->users) && !$refresh) {
			return $this->users[(string)$boardId];
		}

		try {
			$board = $this->boardMapper->find($boardId);
		} catch (DoesNotExistException $e) {
			return [];
		} catch (MultipleObjectsReturnedException $e) {
			return [];
		}

		$users = [];
		if (!$this->userManager->userExists($board->getOwner())) {
			$this->logger->info('No owner found for board ' . $board->getId());
		} else {
			$users[$board->getOwner()] = new User($board->getOwner(), $this->userManager);
		}
		$acls = $this->aclMapper->findAll($boardId);
		/** @var Acl $acl */
		foreach ($acls as $acl) {
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
				if (!$this->userManager->userExists($acl->getParticipant())) {
					$this->logger->info('No user found for acl rule ' . $acl->getId());
					continue;
				}
				$users[$acl->getParticipant()] = new User($acl->getParticipant(), $this->userManager);
			}
			if ($acl->getType() === Acl::PERMISSION_TYPE_GROUP) {
				$group = $this->groupManager->get($acl->getParticipant());
				if ($group === null) {
					$this->logger->info('No group found for acl rule ' . $acl->getId());
					continue;
				}
				foreach ($group->getUsers() as $user) {
					$users[$user->getUID()] = new User($user->getUID(), $this->userManager);
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
							$users[$member->getUserId()] = new User($member->getUserId(), $this->userManager);
						}
					}
				} catch (\Exception $e) {
					$this->logger->info('Member not found in circle that was accessed. This should not happen.');
				}
			}
		}
		$this->users[(string)$boardId] = $users;
		return $this->users[(string)$boardId];
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
