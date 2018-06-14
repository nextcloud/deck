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


use OC\Security\CSP\ContentSecurityPolicyManager;
use OCA\Deck\Db\Attachment;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IL10N;
use OCP\IRequest;


class FileService implements IAttachmentService {

	private $l10n;
	private $appData;
	private $request;

	public function __construct(
		IL10N $l10n,
		IAppData $appData,
		IRequest $request
	) {
		$this->l10n = $l10n;
		$this->appData = $appData;
		$this->request = $request;
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
	private function getFolder(Attachment $attachment) {
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
			// TODO: log error
			return $attachment;
		} catch (NotPermittedException $e) {
			return $attachment;
		}
		$attachment->setExtendedData([
			'filesize' => $file->getSize(),
			'mimetype' => $file->getMimeType(),
			'info' => pathinfo($file->getName())
		]);
		return $attachment;
	}

	private function getUploadedFile () {
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
		$error = $this->l10n->t('No file uploaded');
		}
		if (!empty($file) && array_key_exists('error', $file) && $file['error'] !== UPLOAD_ERR_OK) {
			$error = $phpFileUploadErrors[$file['error']];
		}
		if ($error !== null) {
			throw new \RuntimeException($error);
		}
		return $file;
	}

	public function create(Attachment $attachment) {
		$file = $this->getUploadedFile();
		$folder = $this->getFolder($attachment);
		$fileName = $file['name'];
		if ($folder->fileExists($fileName)) {
			throw new \Exception('File already exists.');
		}
		$target = $folder->newFile($fileName);
		$target->putContent(file_get_contents($file['tmp_name'], 'r'));

		$attachment->setData($fileName);
	}

	/**
	 * This method requires to be used with POST so we can properly get the form data
	 */
	public function update(Attachment $attachment) {
		$file = $this->getUploadedFile();
		$fileName = $file['name'];
		$attachment->setData($fileName);

		$target = $this->getFileForAttachment($attachment);
		$target->putContent(file_get_contents($file['tmp_name'], 'r'));

		$attachment->setLastModified(time());
	}

	public function delete(Attachment $attachment) {
		try {
			$file = $this->getFileForAttachment($attachment);
			$file->delete();
		} catch (NotFoundException $e) {
		}
	}

	public function display(Attachment $attachment) {
		$file = $this->getFileForAttachment($attachment);
		$response = new FileDisplayResponse($file);
		if ($file->getMimeType() === 'application/pdf') {
			// We need those since otherwise chrome won't show the PDF file with CSP rule object-src 'none'
			// https://bugs.chromium.org/p/chromium/issues/detail?id=271452
			$policy = new ContentSecurityPolicy();
			$policy->addAllowedObjectDomain('\'self\'');
			$policy->addAllowedObjectDomain('blob:');
			$response->setContentSecurityPolicy($policy);
		}
		$response->addHeader('Content-Type', $file->getMimeType());
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