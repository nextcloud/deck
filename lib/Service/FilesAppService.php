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
use OCA\Deck\Sharing\DeckShareProvider;
use OCA\Deck\StatusException;
use OCA\Deck\Exceptions\ConflictException;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Constants;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IPreview;
use OCP\IRequest;
use OCP\Share;
use OCP\Share\IManager;
use OCP\Share\IShare;

class FilesAppService implements IAttachmentService, ICustomAttachmentService {

	private $request;
	private $rootFolder;
	private $shareProvider;
	private $shareManager;
	private $userId;
	private $configService;
	private $l10n;
	private $preview;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		IRootFolder $rootFolder,
		IManager $shareManager,
		ConfigService $configService,
		DeckShareProvider $shareProvider,
		IPreview $preview,
		string $userId = null
	) {
		$this->request = $request;
		$this->l10n = $l10n;
		$this->rootFolder = $rootFolder;
		$this->configService = $configService;
		$this->shareProvider = $shareProvider;
		$this->shareManager = $shareManager;
		$this->userId = $userId;
		$this->preview = $preview;
	}

	public function listAttachments(int $cardId): array {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$shares = $this->shareProvider->getSharedWithByType($cardId, IShare::TYPE_DECK, -1, 0);
		return array_map(function (IShare $share) use ($cardId, $userFolder) {
			$file = $share->getNode();
			$nodes = $userFolder->getById($file->getId());
			$file = array_shift($nodes);
			$attachment = new Attachment();
			$attachment->setType('file');
			$attachment->setId($file->getId());
			$attachment->setCardId($cardId);
			$attachment->setCreatedBy($share->getSharedBy());
			$attachment->setData($file->getName());
			$attachment->setLastModified($file->getMTime());
			$attachment->setCreatedAt($share->getShareTime()->getTimestamp());
			$attachment->setDeletedAt(0);
			return $attachment;
		}, $shares);
	}

	function getAttachmentCount(int $cardId): int {
		/** @var IDBConnection $qb */
		$db = \OC::$server->getDatabaseConnection();
		$qb = $db->getQueryBuilder();
		$qb->select($qb->createFunction('count(s.id)'))
			->from('share', 's')
			->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK)))
			->andWhere($qb->expr()->eq('s.share_with', $qb->createNamedParameter($cardId)))
			->andWhere($qb->expr()->isNull('s.parent'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('folder'))
			));

		$cursor = $qb->execute();
		$count = $cursor->fetchColumn(0);
		$cursor->closeCursor();
		return $count;
	}

	public function extendData(Attachment $attachment) {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$nodes = $userFolder->getById($attachment->getId());
		$file = array_shift($nodes);
		$attachment->setExtendedData([
			'path' => $userFolder->getRelativePath($file->getPath()),
			'fileid' => $file->getId(),
			'data' => $file->getName(),
			'filesize' => $file->getSize(),
			'mimetype' => $file->getMimeType(),
			'info' => pathinfo($file->getName()),
			'hasPreview' => $this->preview->isAvailable($file),
		]);
		return $attachment;
	}

	public function display(Attachment $attachment) {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$nodes = $userFolder->getById($attachment->getId());
		$file = array_shift($nodes);
		if ($file === null) {
			throw new NotFoundException('File not found');
		}
		if (method_exists($file, 'fopen')) {
			$response = new StreamResponse($file->fopen('r'));
			$response->addHeader('Content-Disposition', 'inline; filename="' . rawurldecode($file->getName()) . '"');
		} else {
			$response = new FileDisplayResponse($file);
		}
		// We need those since otherwise chrome won't show the PDF file with CSP rule object-src 'none'
		// https://bugs.chromium.org/p/chromium/issues/detail?id=271452
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedObjectDomain('\'self\'');
		$policy->addAllowedObjectDomain('blob:');
		$policy->addAllowedMediaDomain('\'self\'');
		$policy->addAllowedMediaDomain('blob:');
		$response->setContentSecurityPolicy($policy);

		$response->addHeader('Content-Type', $file->getMimeType());
		return $response;
	}

	public function create(Attachment $attachment) {
		$file = $this->getUploadedFile();
		$fileName = $file['name'];

		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$folder = $userFolder->get($this->configService->getAttachmentFolder());

		// FIXME: Add to docs that conflict handling is different here, no ConflictException will be thrown
		$fileName = $folder->getNonExistingName($fileName);
		$target = $folder->newFile($fileName);
		$content = fopen($file['tmp_name'], 'rb');
		if ($content === false) {
			throw new StatusException('Could not read file');
		}
		$target->putContent($content);
		if (is_resource($content)) {
			fclose($content);
		}

		$share = $this->shareManager->newShare();
		$share->setNode($target);
		$share->setShareType(Share::SHARE_TYPE_DECK);
		$share->setSharedWith((string)$attachment->getCardId());
		$share->setPermissions(Constants::PERMISSION_READ);
		$share->setSharedBy($this->userId);
		$this->shareManager->createShare($share);
		$attachment->setId($target->getId());
		$attachment->setData($target->getName());
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

	public function update(Attachment $attachment) {
		// TODO: Implement update() method.
	}

	public function delete(Attachment $attachment) {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$nodes = $userFolder->getById($attachment->getId());
		$file = array_shift($nodes);
		if ($file === null) {
			throw new NotFoundException('File not found');
		}

		if ($file->getOwner() !== null && $file->getOwner()->getUID() === $this->userId) {
			$file->delete();
			return;
		}

		// FIXME: only with manage permissions
		$shares = $this->shareProvider->getSharedWithByType($attachment->getCardId(), IShare::TYPE_DECK, -1, 0);
		foreach ($shares as $share) {
			if ($share->getNode()->getId() === $attachment->getId()) {
				$this->shareManager->deleteShare($share);
				return;
			}
		}

	}

	public function allowUndo() {
		return false;
	}

	public function markAsDeleted(Attachment $attachment) {
		throw new \Exception('Not implemented');
	}
}
