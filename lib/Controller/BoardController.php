<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

class BoardController extends ApiController {
	public function __construct(
		$appName,
		IRequest $request,
		private BoardService $boardService,
		private PermissionService $permissionService,
		private BoardImportService $boardImportService,
		private IL10N $l10n,
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

	/**
	 * @NoAdminRequired
	 */
	public function import(): DataResponse {
		$file = $this->request->getUploadedFile('file');
		$error = null;
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];

		if (empty($file)) {
			$error = $this->l10n->t('No file uploaded or file size exceeds maximum of %s', [\OCP\Util::humanFileSize(\OCP\Util::uploadLimit())]);
		}
		if (!empty($file) && array_key_exists('error', $file) && $file['error'] !== UPLOAD_ERR_OK) {
			$error = $phpFileUploadErrors[$file['error']];
		}
		if (!empty($file) && $file['error'] === UPLOAD_ERR_OK && !in_array($file['type'], ['application/json', 'text/plain'])) {
			$error = $this->l10n->t('Invalid file type. Only JSON files are allowed.');
		}
		if ($error !== null) {
			return new DataResponse([
				'status' => 'error',
				'message' => $error,
			], Http::STATUS_BAD_REQUEST);
		}

		try {
			$fileContent = file_get_contents($file['tmp_name']);
			$this->boardImportService->setSystem('DeckJson');
			$config = new \stdClass();
			$config->owner = $this->userId;
			$this->boardImportService->setConfigInstance($config);
			$this->boardImportService->setData(json_decode($fileContent));
			$this->boardImportService->import();
			$importedBoard = $this->boardImportService->getBoard();
			$board = $this->boardService->find($importedBoard->getId());

			return new DataResponse($board, Http::STATUS_OK);
		} catch (\TypeError $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $this->l10n->t('Invalid JSON data'),
			], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $this->l10n->t('Failed to import board'),
			], Http::STATUS_BAD_REQUEST);
		}
	}
}
