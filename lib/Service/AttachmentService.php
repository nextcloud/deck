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

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCA\Deck\StatusException;
use OCP\AppFramework\Http\Response;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IL10N;

class AttachmentService {
	private $attachmentMapper;
	private $cardMapper;
	private $permissionService;
	private $userId;

	/** @var IAttachmentService[] */
	private $services = [];
	private $application;
	/** @var ICache */
	private $cache;
	/** @var IL10N */
	private $l10n;
	/** @var ActivityManager */
	private $activityManager;
	/** @var ChangeHelper */
	private $changeHelper;

	/**
	 * AttachmentService constructor.
	 *
	 * @param AttachmentMapper $attachmentMapper
	 * @param CardMapper $cardMapper
	 * @param PermissionService $permissionService
	 * @param Application $application
	 * @param ICacheFactory $cacheFactory
	 * @param $userId
	 * @param IL10N $l10n
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function __construct(AttachmentMapper $attachmentMapper, CardMapper $cardMapper, ChangeHelper $changeHelper, PermissionService $permissionService, Application $application, ICacheFactory $cacheFactory, $userId, IL10N $l10n, ActivityManager $activityManager) {
		$this->attachmentMapper = $attachmentMapper;
		$this->cardMapper = $cardMapper;
		$this->permissionService = $permissionService;
		$this->userId = $userId;
		$this->application = $application;
		$this->cache = $cacheFactory->createDistributed('deck-card-attachments-');
		$this->l10n = $l10n;
		$this->activityManager = $activityManager;
		$this->changeHelper = $changeHelper;

		// Register shipped attachment services
		// TODO: move this to a plugin based approach once we have different types of attachments
		$this->registerAttachmentService('deck_file', FileService::class);
	}

	/**
	 * @param string $type
	 * @param string $class
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function registerAttachmentService($type, $class) {
		$this->services[$type] = $this->application->getContainer()->query($class);
	}

	/**
	 * @param string $type
	 * @return IAttachmentService
	 * @throws InvalidAttachmentType
	 */
	public function getService($type) {
		if (isset($this->services[$type])) {
			return $this->services[$type];
		}
		throw new InvalidAttachmentType($type);
	}

	/**
	 * @param $cardId
	 * @return array
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws BadRequestException
	 */
	public function findAll($cardId, $withDeleted = false) {
		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		$attachments = $this->attachmentMapper->findAll($cardId);
		if ($withDeleted) {
			$attachments = array_merge($attachments, $this->attachmentMapper->findToDelete($cardId, false));
		}
		foreach ($attachments as &$attachment) {
			try {
				$service = $this->getService($attachment->getType());
				$service->extendData($attachment);
			} catch (InvalidAttachmentType $e) {
				// Ingore invalid attachment types when extending the data
			}
		}
		return $attachments;
	}

	/**
	 * @param $cardId
	 * @return int|mixed
	 * @throws BadRequestException
	 */
	public function count($cardId) {
		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		$count = $this->cache->get('card-' . $cardId);
		if (!$count) {
			$count = count($this->attachmentMapper->findAll($cardId));
			$this->cache->set('card-' . $cardId, $count);
		}
		return $count;
	}

	/**
	 * @param $cardId
	 * @param $type
	 * @param $data
	 * @return Attachment|\OCP\AppFramework\Db\Entity
	 * @throws NoPermissionException
	 * @throws StatusException
	 * @throws BadRequestException
	 */
	public function create($cardId, $type, $data) {
		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		if ($type === false || $type === null) {
			throw new BadRequestException('type must be provided');
		}

		if ($data === false || $data === null) {
			//throw new BadRequestException('data must be provided');
		}

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);

		$this->cache->clear('card-' . $cardId);
		$attachment = new Attachment();
		$attachment->setCardId($cardId);
		$attachment->setType($type);
		$attachment->setData($data);
		$attachment->setCreatedBy($this->userId);
		$attachment->setLastModified(time());
		$attachment->setCreatedAt(time());

		try {
			$service = $this->getService($attachment->getType());
			$service->create($attachment);
		} catch (InvalidAttachmentType $e) {
			// just store the data
		}
		if ($attachment->getData() === null) {
			throw new StatusException($this->l10n->t('No data was provided to create an attachment.'));
		}
		$attachment = $this->attachmentMapper->insert($attachment);

		// extend data so the frontend can use it properly after creating
		try {
			$service = $this->getService($attachment->getType());
			$service->extendData($attachment);
		} catch (InvalidAttachmentType $e) {
			// just store the data
		}
		$this->changeHelper->cardChanged($attachment->getCardId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_CREATE);
		return $attachment;
	}


	/**
	 * Display the attachment
	 *
	 * @param $attachmentId
	 * @return Response
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function display($attachmentId) {
		if (is_numeric($attachmentId) === false) {
			throw new BadRequestException('attachment id must be a number');
		}

		try {
			$attachment = $this->attachmentMapper->find($attachmentId);
		} catch (\Exception $e) {
			throw new NoPermissionException('Permission denied');
		}
		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_READ);

		try {
			$service = $this->getService($attachment->getType());
			return $service->display($attachment);
		} catch (InvalidAttachmentType $e) {
			throw new NotFoundException();
		}
	}

	/**
	 * Update an attachment with custom data
	 *
	 * @param $attachmentId
	 * @param $request
	 * @return mixed
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update($attachmentId, $data) {
		if (is_numeric($attachmentId) === false) {
			throw new BadRequestException('attachment id must be a number');
		}

		if ($data === false || $data === null) {
			//throw new BadRequestException('data must be provided');
		}
		try {
			$attachment = $this->attachmentMapper->find($attachmentId);
		} catch (\Exception $e) {
			throw new NoPermissionException('Permission denied');
		}

		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);
		$this->cache->clear('card-' . $attachment->getCardId());

		$attachment->setData($data);
		try {
			$service = $this->getService($attachment->getType());
			$service->update($attachment);
		} catch (InvalidAttachmentType $e) {
			// just update without further action
		}
		$attachment->setLastModified(time());
		$this->attachmentMapper->update($attachment);
		// extend data so the frontend can use it properly after creating
		try {
			$service = $this->getService($attachment->getType());
			$service->extendData($attachment);
		} catch (InvalidAttachmentType $e) {
			// just store the data
		}
		$this->changeHelper->cardChanged($attachment->getCardId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_UPDATE);
		return $attachment;
	}

	/**
	 * Either mark an attachment as deleted for later removal or just remove it depending
	 * on the IAttachmentService implementation
	 *
	 * @param $attachmentId
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete($attachmentId) {
		if (is_numeric($attachmentId) === false) {
			throw new BadRequestException('attachment id must be a number');
		}

		try {
			$attachment = $this->attachmentMapper->find($attachmentId);
		} catch (\Exception $e) {
			throw new NoPermissionException('Permission denied');
		}

		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);
		$this->cache->clear('card-' . $attachment->getCardId());

		try {
			$service = $this->getService($attachment->getType());
			if ($service->allowUndo()) {
				$service->markAsDeleted($attachment);
				$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_DELETE);
				$this->changeHelper->cardChanged($attachment->getCardId());
				return $this->attachmentMapper->update($attachment);
			}
			$service->delete($attachment);
		} catch (InvalidAttachmentType $e) {
			// just delete without further action
		}
		$attachment = $this->attachmentMapper->delete($attachment);
		$this->changeHelper->cardChanged($attachment->getCardId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_DELETE);
		return $attachment;
	}

	public function restore($attachmentId) {
		if (is_numeric($attachmentId) === false) {
			throw new BadRequestException('attachment id must be a number');
		}

		try {
			$attachment = $this->attachmentMapper->find($attachmentId);
		} catch (\Exception $e) {
			throw new NoPermissionException('Permission denied');
		}

		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);
		$this->cache->clear('card-' . $attachment->getCardId());

		try {
			$service = $this->getService($attachment->getType());
			if ($service->allowUndo()) {
				$attachment->setDeletedAt(0);
				$attachment = $this->attachmentMapper->update($attachment);
				$this->changeHelper->cardChanged($attachment->getCardId());
				$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_RESTORE);
				return $attachment;
			}
		} catch (InvalidAttachmentType $e) {
		}
		throw new NoPermissionException('Restore is not allowed.');
	}
}
