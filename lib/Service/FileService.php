<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Exceptions\ConflictException;
use OCA\Deck\StatusException;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Files\IAppData;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class FileService implements IAttachmentService {

	public function __construct(
		private IL10N $l10n,
		private IAppData $appData,
		private IRequest $request,
		private LoggerInterface $logger,
		private IRootFolder $rootFolder,
		private IConfig $config,
		private AttachmentMapper $attachmentMapper,
		private IMimeTypeDetector $mimeTypeDetector,
	) {
	}

	/**
	 * @param Attachment $attachment
	 * @return ISimpleFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getFileForAttachment(Attachment $attachment) {
		return $this->getFolder($attachment)
			->getFile($attachment->getData());
	}

	/**
	 * @param Attachment $attachment
	 * @return ISimpleFolder
	 * @throws NotPermittedException
	 */
	public function getFolder(Attachment $attachment) {
		$folderName = 'file-card-' . (int)$attachment->getCardId();
		try {
			$folder = $this->appData->getFolder($folderName);
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder($folderName);
		}
		return $folder;
	}

	public function extendData(Attachment $attachment) {
		try {
			$file = $this->getFileForAttachment($attachment);
		} catch (NotFoundException $e) {
			$this->logger->info('Extending data for file attachment failed');
			return $attachment;
		} catch (NotPermittedException $e) {
			$this->logger->info('Extending data for file attachment failed');
			return $attachment;
		}
		$attachment->setExtendedData([
			'filesize' => $file->getSize(),
			'mimetype' => $file->getMimeType(),
			'info' => pathinfo($file->getName())
		]);
		return $attachment;
	}

	/**
	 * @return array
	 * @throws StatusException
	 */
	private function getUploadedFile() {
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
		if ($error !== null) {
			throw new StatusException($error);
		}
		return $file;
	}

	/**
	 * @param Attachment $attachment
	 * @throws NotPermittedException
	 * @throws StatusException
	 * @throws ConflictException
	 */
	public function create(Attachment $attachment) {
		$file = $this->getUploadedFile();
		$folder = $this->getFolder($attachment);
		$fileName = $file['name'];
		if ($folder->fileExists($fileName)) {
			$attachment = $this->attachmentMapper->findByData($attachment->getCardId(), $fileName);
			throw new ConflictException('File already exists.', $attachment);
		}

		$target = $folder->newFile($fileName);
		$content = fopen($file['tmp_name'], 'rb');
		if ($content === false) {
			throw new StatusException('Could not read file');
		}
		$target->putContent($content);
		if (is_resource($content)) {
			fclose($content);
		}

		$attachment->setData($fileName);
	}

	/**
	 * This method requires to be used with POST so we can properly get the form data
	 *
	 * @throws \Exception
	 */
	public function update(Attachment $attachment) {
		$file = $this->getUploadedFile();
		$fileName = $file['name'];
		$attachment->setData($fileName);

		$target = $this->getFileForAttachment($attachment);
		$content = fopen($file['tmp_name'], 'rb');
		if ($content === false) {
			throw new StatusException('Could not read file');
		}
		$target->putContent($content);
		if (is_resource($content)) {
			fclose($content);
		}

		$attachment->setLastModified(time());
	}

	/**
	 * @param Attachment $attachment
	 * @throws NotPermittedException
	 */
	public function delete(Attachment $attachment) {
		try {
			$file = $this->getFileForAttachment($attachment);
			$file->delete();
		} catch (NotFoundException $e) {
		}
	}

	/**
	 * Workaround until ISimpleFile can be fetched as a resource
	 *
	 * @return \OCP\Files\File
	 * @throws \Exception
	 */
	private function getFileFromRootFolder(Attachment $attachment) {
		$folderName = 'file-card-' . (int)$attachment->getCardId();
		$instanceId = $this->config->getSystemValue('instanceid', null);
		if ($instanceId === null) {
			throw new \Exception('no instance id!');
		}
		$name = 'appdata_' . $instanceId;
		/** @var \OCP\Files\Folder $appDataFolder */
		$appDataFolder = $this->rootFolder->get($name);
		/** @var \OCP\Files\Folder $appDataFolder */
		$appDataFolder = $appDataFolder->get('deck');
		/** @var \OCP\Files\Folder $cardFolder */
		$cardFolder = $appDataFolder->get($folderName);
		return $cardFolder->get($attachment->getData());
	}

	/**
	 * @param Attachment $attachment
	 * @return StreamResponse
	 * @throws \Exception
	 */
	public function display(Attachment $attachment) {
		$file = $this->getFileFromRootFolder($attachment);
		$response = new StreamResponse($file->fopen('rb'));
		$response->addHeader('Content-Disposition', 'attachment; filename="' . rawurldecode($file->getName()) . '"');
		$response->addHeader('Content-Type', $this->mimeTypeDetector->getSecureMimeType($file->getMimeType()));
		return $response;
	}

	/**
	 * Should undo be allowed and the delete action be done by a background job
	 *
	 * @return bool
	 */
	public function allowUndo() {
		return true;
	}

	/**
	 * Mark an attachment as deleted
	 *
	 * @param Attachment $attachment
	 */
	public function markAsDeleted(Attachment $attachment) {
		$attachment->setDeletedAt(time());
	}
}
