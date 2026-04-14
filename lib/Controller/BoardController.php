<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CsvImportService;
use OCA\Deck\Service\ExternalBoardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

class BoardController extends ApiController {
	public function __construct(
		$appName,
		IRequest $request,
		private BoardService $boardService,
		private ExternalBoardService $externalBoardService,
		private PermissionService $permissionService,
		private BoardImportService $boardImportService,
		private CsvImportService $csvImportService,
		private IL10N $l10n,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index() {
		return $this->boardService->findAll();
	}

	#[NoAdminRequired]
	public function read(int $boardId): Board {
		return $this->boardService->find($boardId);
	}

	#[NoAdminRequired]
	public function create(string $title, string $color): Board {
		return $this->boardService->create($title, $this->userId, $color);
	}

	#[NoAdminRequired]
	public function update(int $id, string $title, string $color, bool $archived): Board {
		return $this->boardService->update($id, $title, $color, $archived);
	}

	#[NoAdminRequired]
	public function delete(int $boardId): Board {
		return $this->boardService->delete($boardId);
	}

	#[NoAdminRequired]
	public function deleteUndo(int $boardId): Board {
		return $this->boardService->deleteUndo($boardId);
	}

	#[NoAdminRequired]
	public function leave(int $boardId) {
		return $this->boardService->leave($boardId);
	}

	#[NoAdminRequired]
	public function getUserPermissions(int $boardId): array {
		$permissions = $this->permissionService->getPermissions($boardId);
		return [
			'PERMISSION_READ' => $permissions[Acl::PERMISSION_READ],
			'PERMISSION_EDIT' => $permissions[Acl::PERMISSION_EDIT],
			'PERMISSION_MANAGE' => $permissions[Acl::PERMISSION_MANAGE],
			'PERMISSION_SHARE' => $permissions[Acl::PERMISSION_SHARE]
		];
	}

	/**
	 * @param $participant
	 */
	#[NoAdminRequired]
	public function addAcl(int $boardId, int $type, $participant, bool $permissionEdit, bool $permissionShare, bool $permissionManage, ?string $remote = null): Acl {
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
		if (!$this->permissionService->canCreate()) {
			throw new NoPermissionException('Creating boards has been disabled for your account.');
		}

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
		$isCsv = $this->isCsvFile($file);
		if (!empty($file) && $file['error'] === UPLOAD_ERR_OK && !$isCsv && !in_array($file['type'], ['application/json', 'text/plain'], true)) {
			$error = $this->l10n->t('Invalid file type. Only JSON and CSV files are allowed.');
		}
		if ($error !== null) {
			return new DataResponse([
				'status' => 'error',
				'message' => $error,
			], Http::STATUS_BAD_REQUEST);
		}

		try {
			$fileContent = file_get_contents($file['tmp_name']);

			if ($isCsv) {
				$boardTitle = pathinfo($file['name'] ?? 'Imported Board', PATHINFO_FILENAME) ?: 'Imported Board';
				$this->boardImportService->setSystem('DeckCsv');
				$config = new \stdClass();
				$config->owner = $this->userId;
				$config->boardTitle = $boardTitle;
				$this->boardImportService->setConfigInstance($config);
				$data = new \stdClass();
				$data->rawCsvContent = $fileContent;
				$data->title = $boardTitle;
				$this->boardImportService->setData($data);
			} else {
				$this->boardImportService->setSystem('DeckJson');
				$config = new \stdClass();
				$config->owner = $this->userId;
				$this->boardImportService->setConfigInstance($config);
				$this->boardImportService->setData(json_decode($fileContent));
			}

			$importErrors = [];
			$this->boardImportService->registerErrorCollector(function (string $message) use (&$importErrors) {
				$importErrors[] = $message;
			});

			$this->boardImportService->import();
			$importedBoard = $this->boardImportService->getBoard();
			$board = $this->boardService->find($importedBoard->getId());

			return new DataResponse([
				'board' => $board,
				'import' => [
					'errors' => $importErrors,
				],
			], Http::STATUS_OK);
		} catch (\TypeError $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $this->l10n->t('Invalid import data'),
			], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $this->l10n->t('Failed to import board'),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function importCsv(int $boardId): DataResponse {
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
		if (!empty($file) && $file['error'] === UPLOAD_ERR_OK && !$this->isCsvFile($file)) {
			$error = $this->l10n->t('Invalid file type. Only CSV files are allowed.');
		}
		if ($error !== null) {
			return new DataResponse([
				'status' => 'error',
				'message' => $error,
			], Http::STATUS_BAD_REQUEST);
		}

		try {
			$fileContent = file_get_contents($file['tmp_name']);
			$importResult = $this->csvImportService->importToBoard($boardId, $fileContent, $this->userId);
			$board = $this->boardService->find($boardId);

			return new DataResponse([
				'board' => $board,
				'import' => $importResult,
			], Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $this->l10n->t('Failed to import cards from CSV'),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	private function isCsvFile(?array $file): bool {
		if (empty($file)) {
			return false;
		}
		if (in_array($file['type'] ?? '', ['text/csv', 'application/csv'], true)) {
			return true;
		}
		$name = $file['name'] ?? '';
		return str_ends_with(strtolower($name), '.csv');
	}
}
