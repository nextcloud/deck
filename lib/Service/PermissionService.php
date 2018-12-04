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

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\IPermissionMapper;
use OCA\Deck\Db\User;
use OCA\Deck\NoPermissionException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserManager;


class PermissionService {

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
	/** @var string */
	private $userId;
	/** @var array */
	private $users = [];

	public function __construct(
		ILogger $logger,
		AclMapper $aclMapper,
		BoardMapper $boardMapper,
		IUserManager $userManager,
		IGroupManager $groupManager,
		$userId
	) {
		$this->aclMapper = $aclMapper;
		$this->boardMapper = $boardMapper;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->userId = $userId;
	}

	/**
	 * Get current user permissions for a board by id
	 *
	 * @param $boardId
	 * @return bool|array
	 */
	public function getPermissions($boardId) {
		$owner = $this->userIsBoardOwner($boardId);
		$acls = $this->aclMapper->findAll($boardId);
		return [
			Acl::PERMISSION_READ => $owner || $this->userCan($acls, Acl::PERMISSION_READ),
			Acl::PERMISSION_EDIT => $owner || $this->userCan($acls, Acl::PERMISSION_EDIT),
			Acl::PERMISSION_MANAGE => $owner || $this->userCan($acls, Acl::PERMISSION_MANAGE),
			Acl::PERMISSION_SHARE => ($owner || $this->userCan($acls, Acl::PERMISSION_SHARE))
				&& (!\OC::$server->getShareManager()->sharingDisabledForUser($this->userId))
		];
	}

	/**
	 * Get current user permissions for a board
	 *
	 * @param Board|Entity $board
	 * @return array|bool
	 * @internal param $boardId
	 */
	public function matchPermissions(Board $board) {
		$owner = $this->userIsBoardOwner($board->getId());
		$acls = $board->getAcl();
		return [
			Acl::PERMISSION_READ => $owner || $this->userCan($acls, Acl::PERMISSION_READ),
			Acl::PERMISSION_EDIT => $owner || $this->userCan($acls, Acl::PERMISSION_EDIT),
			Acl::PERMISSION_MANAGE => $owner || $this->userCan($acls, Acl::PERMISSION_MANAGE),
			Acl::PERMISSION_SHARE => ($owner || $this->userCan($acls, Acl::PERMISSION_SHARE))
				&& (!\OC::$server->getShareManager()->sharingDisabledForUser($this->userId))
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
	public function checkPermission($mapper, $id, $permission) {
		$boardId = $id;
		if ($mapper instanceof IPermissionMapper) {
			$boardId = $mapper->findBoardId($id);
		}
		if ($boardId === null) {
			// Throw NoPermission to not leak information about existing entries
			throw new NoPermissionException('Permission denied');
		}

		if ($permission === Acl::PERMISSION_SHARE && !\OC::$server->getShareManager()->sharingDisabledForUser($this->userId)) {
			return false;
		}

		if ($this->userIsBoardOwner($boardId)) {
			return true;
		}

		$acls = $this->aclMapper->findAll($boardId);
		$result = $this->userCan($acls, $permission);
		if ($result) {
			return true;
		}

		// Throw NoPermission to not leak information about existing entries
		throw new NoPermissionException('Permission denied');
	}

	/**
	 * @param $boardId
	 * @return bool
	 */
	public function userIsBoardOwner($boardId) {
		try {
			$board = $this->boardMapper->find($boardId);
			return $board && $this->userId === $board->getOwner();
		} catch (DoesNotExistException $e) {
		} catch (MultipleObjectsReturnedException $e) {
			return false;
		}
	}

	/**
	 * Check if permission matches the acl rules for current user and groups
	 *
	 * @param Acl[] $acls
	 * @param $permission
	 * @return bool
	 */
	public function userCan(array $acls, $permission) {
		// check for users
		foreach ($acls as $acl) {
			if ($acl->getType() === Acl::PERMISSION_TYPE_USER && $acl->getParticipant() === $this->userId) {
				return $acl->getPermission($permission);
			}
		}
		// check for groups
		$hasGroupPermission = false;
		foreach ($acls as $acl) {
			if (!$hasGroupPermission && $acl->getType() === Acl::PERMISSION_TYPE_GROUP && $this->groupManager->isInGroup($this->userId, $acl->getParticipant())) {
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
	public function findUsers($boardId) {
		// cache users of a board so we don't query them for every cards
		if (array_key_exists((string) $boardId, $this->users)) {
			return $this->users[(string) $boardId];
		}
		try {
			$board = $this->boardMapper->find($boardId);
		} catch (DoesNotExistException $e) {
			return [];
		} catch (MultipleObjectsReturnedException $e) {
			return [];
		}

		$owner = $this->userManager->get($board->getOwner());
		if ($owner === null) {
			$this->logger->info('No owner found for board ' . $board->getId());
		} else {
			$users = [];
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
		}
		$this->users[(string) $boardId] = $users;
		return $this->users[(string) $boardId];
	}
}