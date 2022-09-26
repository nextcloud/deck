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
use OCA\Deck\Cache\AttachmentCacheHelper;
use OCA\Deck\StatusException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IUserManager;

class AttachmentService {
	private $attachmentMapper;
	private $cardMapper;
	private $permissionService;
	private $userId;

	/** @var IAttachmentService[] */
	private $services = [];
	/** @var Application */
	private $application;
	/** @var AttachmentCacheHelper */
	private $attachmentCacheHelper;
	/** @var IL10N */
	private $l10n;
	/** @var ActivityManager */
	private $activityManager;
	/** @var ChangeHelper */
	private $changeHelper;
	private IUserManager $userManager;

	public function __construct(AttachmentMapper $attachmentMapper,
								CardMapper $cardMapper,
								IUserManager $userManager,
								ChangeHelper $changeHelper,
								PermissionService $permissionService,
								Application $application,
								AttachmentCacheHelper $attachmentCacheHelper,
								$userId,
								IL10N $l10n,
								ActivityManager $activityManager) {
		$this->attachmentMapper = $attachmentMapper;
		$this->cardMapper = $cardMapper;
		$this->permissionService = $permissionService;
		$this->userId = $userId;
		$this->application = $application;
		$this->attachmentCacheHelper = $attachmentCacheHelper;
		$this->l10n = $l10n;
		$this->activityManager = $activityManager;
		$this->changeHelper = $changeHelper;
		$this->userManager = $userManager;

		// Register shipped attachment services
		// TODO: move this to a plugin based approach once we have different types of attachments
		$this->registerAttachmentService('deck_file', FileService::class);
		$this->registerAttachmentService('file', FilesAppService::class);
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

		foreach (array_keys($this->services) as $attachmentType) {
			/** @var IAttachmentService $service */
			$service = $this->getService($attachmentType);
			if ($service instanceof ICustomAttachmentService) {
				$attachments = array_merge($attachments, $service->listAttachments((int)$cardId));
			}
		}

		foreach ($attachments as &$attachment) {
			try {
				$service = $this->getService($attachment->getType());
				$service->extendData($attachment);
				$this->addCreator($attachment);
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
	 * @throws InvalidAttachmentType
	 * @throws \OCP\DB\Exception
	 */
	public function count($cardId) {
		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		$count = $this->attachmentCacheHelper->getAttachmentCount((int)$cardId);
		if ($count === null) {
			$count = count($this->attachmentMapper->findAll($cardId));

			foreach (array_keys($this->services) as $attachmentType) {
				$service = $this->getService($attachmentType);
				if ($service instanceof ICustomAttachmentService) {
					$count += $service->getAttachmentCount((int)$cardId);
				}
			}

			$this->attachmentCacheHelper->setAttachmentCount((int)$cardId, $count);
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

		$this->attachmentCacheHelper->clearAttachmentCount((int)$cardId);
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

			if (!$service instanceof ICustomAttachmentService) {
				if ($attachment->getData() === null) {
					throw new StatusException($this->l10n->t('No data was provided to create an attachment.'));
				}

				$attachment = $this->attachmentMapper->insert($attachment);
			}

			$service->extendData($attachment);
			$this->addCreator($attachment);
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
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	public function display($cardId, $attachmentId, $type = 'deck_file') {
		try {
			$service = $this->getService($type);
		} catch (InvalidAttachmentType $e) {
			throw new NotFoundException();
		}

		if (!$service instanceof ICustomAttachmentService) {
			try {
				$attachment = $this->attachmentMapper->find($attachmentId);
			} catch (\Exception $e) {
				throw new NoPermissionException('Permission denied');
			}
			$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_READ);

			try {
				$service = $this->getService($attachment->getType());
			} catch (InvalidAttachmentType $e) {
				throw new NotFoundException();
			}
		} else {
			$attachment = new Attachment();
			$attachment->setId($attachmentId);
			$attachment->setType($type);
			$attachment->setCardId($cardId);
			$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_READ);
		}

		return $service->display($attachment);
	}

	/**
	 * Update an attachment with custom data
	 *
	 * @param $attachmentId
	 * @param $data
	 * @return mixed
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 */
	public function update($cardId, $attachmentId, $data, $type = 'deck_file') {
		try {
			$service = $this->getService($type);
		} catch (InvalidAttachmentType $e) {
			throw new NotFoundException();
		}

		if ($service instanceof ICustomAttachmentService) {
			try {
				$attachment = new Attachment();
				$attachment->setId($attachmentId);
				$attachment->setType($type);
				$attachment->setData($data);
				$attachment->setCardId($cardId);
				$service->update($attachment);
				$this->changeHelper->cardChanged($attachment->getCardId());
				return $attachment;
			} catch (\Exception $e) {
				throw new NotFoundException();
			}
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
		$this->attachmentCacheHelper->clearAttachmentCount($cardId);

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
		$service->extendData($attachment);
		$this->addCreator($attachment);

		$this->changeHelper->cardChanged($attachment->getCardId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_UPDATE);
		return $attachment;
	}

	/**
	 * Either mark an attachment as deleted for later removal or just remove it depending
	 * on the IAttachmentService implementation
	 *
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	public function delete(int $cardId, int $attachmentId, string $type = 'deck_file'): Attachment {
		try {
			$service = $this->getService($type);
		} catch (InvalidAttachmentType $e) {
			throw new NotFoundException();
		}

		if ($service instanceof ICustomAttachmentService) {
			$attachment = new Attachment();
			$attachment->setId($attachmentId);
			$attachment->setType($type);
			$attachment->setCardId($cardId);
			$service->extendData($attachment);
		} else {
			try {
				$attachment = $this->attachmentMapper->find($attachmentId);
			} catch (IMapperException $e) {
				throw new NoPermissionException('Permission denied');
			}
		}
		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);

		if ($service->allowUndo()) {
			$service->markAsDeleted($attachment);
			$attachment = $this->attachmentMapper->update($attachment);
		} else {
			$service->delete($attachment);
			if (!$service instanceof ICustomAttachmentService) {
				$attachment = $this->attachmentMapper->delete($attachment);
			}
		}

		$this->attachmentCacheHelper->clearAttachmentCount($cardId);
		$this->changeHelper->cardChanged($attachment->getCardId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_DELETE);
		return $attachment;
	}

	public function restore(int $cardId, int $attachmentId, string $type = 'deck_file'): Attachment {
		try {
			$attachment = $this->attachmentMapper->find($attachmentId);
		} catch (\Exception $e) {
			throw new NoPermissionException('Permission denied');
		}

		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);
		$this->attachmentCacheHelper->clearAttachmentCount($cardId);

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

	/**
	 * @param Attachment $attachment
	 * @return Attachment
	 * @throws \ReflectionException
	 */
	private function addCreator(Attachment $attachment): Attachment {
		$createdBy = $attachment->jsonSerialize()['createdBy'] ?? '';
		$creator = [
			'displayName' => $createdBy,
			'id' => $createdBy,
			'email' => null,
		];
		if ($this->userManager->userExists($createdBy)) {
			$user = $this->userManager->get($createdBy);
			$creator['displayName'] = $user->getDisplayName();
			$creator['email'] = $user->getEMailAddress();
		}
		$extendedData = $attachment->jsonSerialize()['extendedData'] ?? [];
		$extendedData['attachmentCreator'] = $creator;
		$attachment->setExtendedData($extendedData);

		return $attachment;
	}
}
