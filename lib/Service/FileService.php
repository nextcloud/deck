<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\StatusException;
use OCA\Deck\Exceptions\ConflictException;
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
use OCP\ILogger;
use OCP\IRequest;

class FileService implements IAttachmentService {
	private $l10n;
	private $appData;
	private $request;
	private $logger;
	private $rootFolder;
	private $config;
	private $attachmentMapper;
	private $mimeTypeDetector;

	public function __construct(
		IL10N $l10n,
		IAppData $appData,
		IRequest $request,
		ILogger $logger,
		IRootFolder $rootFolder,
		IConfig $config,
		AttachmentMapper $attachmentMapper,
		IMimeTypeDetector $mimeTypeDetector
	) {
		$this->l10n = $l10n;
		$this->appData = $appData;
		$this->request = $request;
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->config = $config;
		$this->attachmentMapper = $attachmentMapper;
		$this->mimeTypeDetector = $mimeTypeDetector;
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
