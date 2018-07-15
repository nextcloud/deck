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
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\IPermissionMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Notification\NotificationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;


class BoardService {

	private $boardMapper;
	private $labelMapper;
	private $aclMapper;
	private $l10n;
	private $permissionService;
	private $notificationHelper;
	private $assignedUsersMapper;

	public function __construct(
		BoardMapper $boardMapper,
		IL10N $l10n,
		LabelMapper $labelMapper,
		AclMapper $aclMapper,
		PermissionService $permissionService,
		NotificationHelper $notificationHelper,
		AssignedUsersMapper $assignedUsersMapper
	) {
		$this->boardMapper = $boardMapper;
		$this->labelMapper = $labelMapper;
		$this->aclMapper = $aclMapper;
		$this->l10n = $l10n;
		$this->permissionService = $permissionService;
		$this->notificationHelper = $notificationHelper;
		$this->assignedUsersMapper = $assignedUsersMapper;
	}

	public function findAll($userInfo) {
		$userBoards = $this->boardMapper->findAllByUser($userInfo['user']);
		$groupBoards = $this->boardMapper->findAllByGroups($userInfo['user'], $userInfo['groups']);
		$complete = array_merge($userBoards, $groupBoards);
		$result = [];
		foreach ($complete as &$item) {
			if (!array_key_exists($item->getId(), $result)) {
				$this->boardMapper->mapOwner($item);
				if ($item->getAcl() !== null) {
					foreach ($item->getAcl() as &$acl) {
						$this->boardMapper->mapAcl($acl);
					}
				}
				$permissions = $this->permissionService->matchPermissions($item);
				$item->setPermissions([
					'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ],
					'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT],
					'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE],
					'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE]
				]);
				$result[$item->getId()] = $item;
			}
		}
		return array_values($result);
	}

	public function find($boardId) {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		/** @var Board $board */
		$board = $this->boardMapper->find($boardId, true, true);
		$this->boardMapper->mapOwner($board);
		foreach ($board->getAcl() as &$acl) {
			if ($acl !== null) {
				$this->boardMapper->mapAcl($acl);
			}
		}
		$permissions = $this->permissionService->matchPermissions($board);
		$board->setPermissions([
			'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ],
			'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT],
			'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE],
			'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE]
		]);
		$boardUsers = $this->permissionService->findUsers($boardId);
		$board->setUsers(array_values($boardUsers));
		return $board;
	}

	public function isArchived($mapper, $id) {
		try {
			$boardId = $id;
			if ($mapper instanceof IPermissionMapper) {
				$boardId = $mapper->findBoardId($id);
			}
			if ($boardId === null) {
				return false;
			}
		} catch (DoesNotExistException $exception) {
			return false;
		}
		$board = $this->find($boardId);
		return $board->getArchived();
	}

	public function isDeleted($mapper, $id) {
		try {
			$boardId = $id;
			if ($mapper instanceof IPermissionMapper) {
				$boardId = $mapper->findBoardId($id);
			}
			if ($boardId === null) {
				return false;
			}
		} catch (DoesNotExistException $exception) {
			return false;
		}
		$board = $this->find($boardId);
		return $board->getDeletedAt() > 0;
	}



	public function create($title, $userId, $color) {
		$board = new Board();
		$board->setTitle($title);
		$board->setOwner($userId);
		$board->setColor($color);
		$new_board = $this->boardMapper->insert($board);

		// create new labels
		$default_labels = [
			'31CC7C' => $this->l10n->t('Finished'),
			'317CCC' => $this->l10n->t('To review'),
			'FF7A66' => $this->l10n->t('Action needed'),
			'F1DB50' => $this->l10n->t('Later')
		];
		$labels = [];
		foreach ($default_labels as $labelColor => $labelTitle) {
			$label = new Label();
			$label->setColor($labelColor);
			$label->setTitle($labelTitle);
			$label->setBoardId($new_board->getId());
			$labels[] = $this->labelMapper->insert($label);
		}
		$new_board->setLabels($labels);
		$this->boardMapper->mapOwner($new_board);
		$permissions = $this->permissionService->matchPermissions($new_board);
		$new_board->setPermissions([
			'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ],
			'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT],
			'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE],
			'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE]
		]);
		return $new_board;

	}

	public function delete($id) {
		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_READ);
		$board = $this->find($id);
		$board->setDeletedAt(time());
		$this->boardMapper->update($board);
		return $board;
	}

	public function deleteUndo($id) {
		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_READ);
		$board = $this->find($id);
		$board->setDeletedAt(0);
		return $this->boardMapper->update($board);
	}

	public function deleteForce($id) {
		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_READ);
		$board = $this->find($id);
		return $this->boardMapper->delete($board);
	}

	public function update($id, $title, $color, $archived) {
		$this->permissionService->checkPermission($this->boardMapper, $id, Acl::PERMISSION_MANAGE);
		$board = $this->find($id);
		$board->setTitle($title);
		$board->setColor($color);
		$board->setArchived($archived);
		$this->boardMapper->mapOwner($board);
		return $this->boardMapper->update($board);
	}


	public function addAcl($boardId, $type, $participant, $edit, $share, $manage) {
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_SHARE);
		$acl = new Acl();
		$acl->setBoardId($boardId);
		$acl->setType($type);
		$acl->setParticipant($participant);
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
		$acl->setPermissionManage($manage);

		/* Notify users about the shared board */
		$this->notificationHelper->sendBoardShared($boardId, $acl);

		$newAcl = $this->aclMapper->insert($acl);
		$this->boardMapper->mapAcl($newAcl);
		return $newAcl;
	}

	public function updateAcl($id, $edit, $share, $manage) {
		$this->permissionService->checkPermission($this->aclMapper, $id, Acl::PERMISSION_SHARE);
		/** @var Acl $acl */
		$acl = $this->aclMapper->find($id);
		$acl->setPermissionEdit($edit);
		$acl->setPermissionShare($share);
		$acl->setPermissionManage($manage);
		$this->boardMapper->mapAcl($acl);
		return $this->aclMapper->update($acl);
	}

	public function deleteAcl($id) {
		$this->permissionService->checkPermission($this->aclMapper, $id, Acl::PERMISSION_SHARE);
		/** @var Acl $acl */
		$acl = $this->aclMapper->find($id);
		$this->boardMapper->mapAcl($acl);
		if ($acl->getType() === Acl::PERMISSION_TYPE_USER) {
			$assignements = $this->assignedUsersMapper->findByUserId($acl->getParticipant());
			foreach ($assignements as $assignement) {
				$this->assignedUsersMapper->delete($assignement);
			}
		}
		return $this->aclMapper->delete($acl);
	}

}
