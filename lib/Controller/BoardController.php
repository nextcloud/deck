<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class BoardController extends ApiController {
	public function __construct(
		$appName,
		IRequest $request,
		private BoardService $boardService,
		private PermissionService $permissionService,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function index() {
		return $this->boardService->findAll();
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function read(int $boardId) {
		return $this->boardService->find($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $title
	 * @param $color
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function create($title, $color) {
		return $this->boardService->create($title, $this->userId, $color);
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $title
	 * @param $color
	 * @param $archived
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function update($id, $title, $color, $archived) {
		return $this->boardService->update($id, $title, $color, $archived);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function delete($boardId) {
		return $this->boardService->delete($boardId);
	}
	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function deleteUndo($boardId) {
		return $this->boardService->deleteUndo($boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return array|bool
	 * @internal param $userId
	 */
	public function getUserPermissions($boardId) {
		$permissions = $this->permissionService->getPermissions($boardId);
		return [
			'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ],
			'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT],
			'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE],
			'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE]
		];
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @param $type
	 * @param $participant
	 * @param $permissionEdit
	 * @param $permissionShare
	 * @param $permissionManage
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function addAcl($boardId, $type, $participant, $permissionEdit, $permissionShare, $permissionManage) {
		return $this->boardService->addAcl($boardId, $type, $participant, $permissionEdit, $permissionShare, $permissionManage);
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $permissionEdit
	 * @param $permissionShare
	 * @param $permissionManage
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function updateAcl($id, $permissionEdit, $permissionShare, $permissionManage) {
		return $this->boardService->updateAcl($id, $permissionEdit, $permissionShare, $permissionManage);
	}

	/**
	 * @NoAdminRequired
	 * @param $aclId
	 * @return \OCP\AppFramework\Db\Entity|null
	 */
	public function deleteAcl($aclId) {
		return $this->boardService->deleteAcl($aclId);
	}

	/**
	 * @NoAdminRequired
	 */
	public function clone(int $boardId, bool $withCards = false, bool $withAssignments = false, bool $withLabels = false, bool $withDueDate = false, bool $moveCardsToLeftStack = false, bool $restoreArchivedCards = false): DataResponse {
		return new DataResponse(
			$this->boardService->clone($boardId, $this->userId, $withCards, $withAssignments, $withLabels, $withDueDate, $moveCardsToLeftStack, $restoreArchivedCards)
		);
	}

	/**
	 * @NoAdminRequired
	 */
	public function transferOwner(int $boardId, string $newOwner): DataResponse {
		if ($this->permissionService->userIsBoardOwner($boardId, $this->userId)) {
			return new DataResponse($this->boardService->transferBoardOwnership($boardId, $newOwner), HTTP::STATUS_OK);
		}

		return new DataResponse([], HTTP::STATUS_UNAUTHORIZED);
	}

	/**
	 * @NoAdminRequired
	 * @param $boardId
	 * @return Board
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function export($boardId) {
		return $this->boardService->export($boardId);
	}
}
