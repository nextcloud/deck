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

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Sharing\DeckShareProvider;
use OCA\Deck\StatusException;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Constants;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class FilesAppService implements IAttachmentService, ICustomAttachmentService {
	private IRequest $request;
	private IRootFolder $rootFolder;
	private DeckShareProvider $shareProvider;
	private IManager $shareManager;
	private ?string $userId;
	private ConfigService $configService;
	private IL10N $l10n;
	private IPreview $preview;
	private IMimeTypeDetector $mimeTypeDetector;
	private PermissionService $permissionService;
	private CardMapper $cardMapper;
	private LoggerInterface $logger;
	private IDBConnection $connection;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		IRootFolder $rootFolder,
		IManager $shareManager,
		ConfigService $configService,
		DeckShareProvider $shareProvider,
		IPreview $preview,
		IMimeTypeDetector $mimeTypeDetector,
		PermissionService $permissionService,
		CardMapper  $cardMapper,
		LoggerInterface $logger,
		IDBConnection $connection,
		?string $userId
	) {
		$this->request = $request;
		$this->l10n = $l10n;
		$this->rootFolder = $rootFolder;
		$this->configService = $configService;
		$this->shareProvider = $shareProvider;
		$this->shareManager = $shareManager;
		$this->userId = $userId;
		$this->preview = $preview;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->permissionService = $permissionService;
		$this->cardMapper = $cardMapper;
		$this->logger = $logger;
		$this->connection = $connection;
	}

	public function listAttachments(int $cardId): array {
		$shares = $this->shareProvider->getSharedWithByType($cardId, IShare::TYPE_DECK, -1, 0);
		return array_filter(array_map(function (IShare $share) use ($cardId) {
			try {
				$file = $share->getNode();
			} catch (NotFoundException $e) {
				$this->logger->debug('Unable to find node for share with ID ' . $share->getId());
				return null;
			}
			$attachment = new Attachment();
			$attachment->setType('file');
			$attachment->setId((int)$share->getId());
			$attachment->setCardId($cardId);
			$attachment->setCreatedBy($share->getSharedBy());
			$attachment->setData($file->getName());
			$attachment->setLastModified($file->getMTime());
			$attachment->setCreatedAt($share->getShareTime()->getTimestamp());
			$attachment->setDeletedAt($share->getPermissions() === 0 ? $share->getShareTime()->getTimestamp() : 0);
			return $attachment;
		}, $shares));
	}

	public function getAttachmentCount(int $cardId): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('s.id', 'f.fileid', 'f.path')
			->selectAlias('st.id', 'storage_string_id')
			->from('share', 's')
			->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
			->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
			->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_DECK)))
			->andWhere($qb->expr()->eq('s.share_with', $qb->createNamedParameter($cardId)))
			->andWhere($qb->expr()->isNull('s.parent'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('folder'))
			));

		$count = 0;
		$cursor = $qb->executeQuery();
		while ($data = $cursor->fetch()) {
			if ($this->shareProvider->isAccessibleResult($data)) {
				$count++;
			}
		}
		$cursor->closeCursor();
		return $count;
	}

	public function extendData(Attachment $attachment) {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$share = $this->shareProvider->getShareById($attachment->getId());
		$files = $userFolder->getById($share->getNode()->getId());
		if (count($files) === 0) {
			return $attachment;
		}
		$file = array_shift($files);
		$attachment->setExtendedData([
			'path' => $userFolder->getRelativePath($file->getPath()),
			'fileid' => $file->getId(),
			'data' => $file->getName(),
			'filesize' => $file->getSize(),
			'mimetype' => $file->getMimeType(),
			'info' => pathinfo($file->getName()),
			'hasPreview' => $this->preview->isAvailable($file),
			'permissions' => $share->getPermissions(),
		]);
		return $attachment;
	}

	public function display(Attachment $attachment) {
		// Problem: Folders
		/** @psalm-suppress InvalidCatch */
		try {
			$share = $this->shareProvider->getShareById($attachment->getId());
		} catch (ShareNotFound $e) {
			throw new NotFoundException('File not found');
		}
		$file = $share->getNode();
		if ($file === null || $share->getSharedWith() !== (string)$attachment->getCardId()) {
			throw new NotFoundException('File not found');
		}

		$response = new StreamResponse($file->fopen('rb'));
		$response->addHeader('Content-Disposition', 'attachment; filename="' . rawurldecode($file->getName()) . '"');
		$response->addHeader('Content-Type', $this->mimeTypeDetector->getSecureMimeType($file->getMimeType()));
		return $response;
	}

	public function create(Attachment $attachment) {
		$file = $this->getUploadedFile();
		$fileName = $file['name'];

		// get shares for current card
		// check if similar filename already exists

		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		try {
			$folder = $userFolder->get($this->configService->getAttachmentFolder());
		} catch (NotFoundException $e) {
			$folder = $userFolder->newFolder($this->configService->getAttachmentFolder());
		}

		$fileName = $folder->getNonExistingName($fileName);
		$target = $folder->newFile($fileName);
		$content = fopen($file['tmp_name'], 'rb');
		if ($content === false) {
			throw new StatusException('Could not read file');
		}
		$target->putContent($content);

		$share = $this->shareManager->newShare();
		$share->setNode($target);
		$share->setShareType(ISHARE::TYPE_DECK);
		$share->setSharedWith((string)$attachment->getCardId());
		$share->setPermissions(Constants::PERMISSION_READ);
		$share->setSharedBy($this->userId);
		$share = $this->shareManager->createShare($share);
		$attachment->setId((int)$share->getId());
		$attachment->setData($target->getName());
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

	public function update(Attachment $attachment) {
		$share = $this->shareProvider->getShareById($attachment->getId());
		$target = $share->getNode();
		$file = $this->getUploadedFile();
		$fileName = $file['name'];
		$attachment->setData($fileName);

		$content = fopen($file['tmp_name'], 'rb');
		if ($content === false) {
			throw new StatusException('Could not read file');
		}
		$target->putContent($content);
		fclose($content);

		$attachment->setLastModified(time());
		return $attachment;
	}

	public function delete(Attachment $attachment) {
		$share = $this->shareProvider->getShareById($attachment->getId());
		$file = $share->getNode();
		$attachment->setData($file->getName());

		// Deleting a Nextcloud file attachment will remove the share to the card, keeping the source file untouched
		// Opt-out of individual shares per user is no longer performed within deck but can still be done through the files app
		$canEdit = $this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);
		$isFileOwner = $file->getOwner() !== null && $file->getOwner()->getUID() === $this->userId;
		if ($isFileOwner || $canEdit) {
			$this->shareManager->deleteShare($share);
			return;
		}

		throw new NoPermissionException('No permission to remove the attachment from the card');
	}

	public function allowUndo() {
		return false;
	}

	public function markAsDeleted(Attachment $attachment) {
		throw new \Exception('Not implemented');
	}
}
