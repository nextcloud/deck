<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Cache\AttachmentCacheHelper;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\AttachmentServiceValidator;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Container\ContainerExceptionInterface;

class AttachmentService {
	/** @var IAttachmentService[] */
	private array $services = [];

	public function __construct(
		private readonly AttachmentMapper $attachmentMapper,
		private readonly CardMapper $cardMapper,
		private readonly IUserManager $userManager,
		private readonly ChangeHelper $changeHelper,
		private readonly PermissionService $permissionService,
		private readonly Application $application,
		private readonly AttachmentCacheHelper $attachmentCacheHelper,
		private readonly ?string $userId,
		private readonly IL10N $l10n,
		private readonly ActivityManager $activityManager,
		private readonly AttachmentServiceValidator $attachmentServiceValidator,
	) {
		// Register shipped attachment services
		// TODO: move this to a plugin based approach once we have different types of attachments
		$this->registerAttachmentService('deck_file', FileService::class);
		$this->registerAttachmentService('file', FilesAppService::class);
	}

	/**
	 * @throws ContainerExceptionInterface
	 */
	public function registerAttachmentService(string $type, string $class): void {
		$this->services[$type] = $this->application->getContainer()->get($class);
	}

	/**
	 * @throws InvalidAttachmentType
	 */
	public function getService(string $type): IAttachmentService {
		if (isset($this->services[$type])) {
			return $this->services[$type];
		}
		throw new InvalidAttachmentType($type);
	}

	/**
	 * @return Attachment[]
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws BadRequestException
	 */
	public function findAll(int $cardId, bool $withDeleted = false): array {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		$attachments = $this->attachmentMapper->findAll($cardId);
		if ($withDeleted) {
			$attachments = array_merge($attachments, $this->attachmentMapper->findToDelete($cardId, false));
		}

		foreach (array_keys($this->services) as $attachmentType) {
			/** @var IAttachmentService $service */
			$service = $this->getService($attachmentType);
			if ($service instanceof ICustomAttachmentService) {
				$attachments = array_merge($attachments, $service->listAttachments($cardId));
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
	 * @throws BadRequestException
	 * @throws InvalidAttachmentType
	 * @throws \OCP\DB\Exception
	 */
	public function count(int $cardId): int {
		return ($this->countForCards([$cardId]))[$cardId] ?? 0;
	}

	/**
	 * Returns a map of cardId => attachment count for the given card IDs using batch queries.
	 *
	 * @param int[] $cardIds
	 * @return array<int, int>
	 * @throws \OCP\DB\Exception
	 */
	public function countForCards(array $cardIds): array {
		if (empty($cardIds)) {
			return [];
		}

		$counts = [];
		$uncachedIds = [];
		foreach ($cardIds as $cardId) {
			$cached = $this->attachmentCacheHelper->getAttachmentCount($cardId);
			if ($cached !== null) {
				$counts[$cardId] = $cached;
			} else {
				$uncachedIds[] = $cardId;
				$counts[$cardId] = 0;
			}
		}

		if (empty($uncachedIds)) {
			return $counts;
		}

		// Batch query for deck_file attachments stored in the deck_attachment table
		foreach ($this->attachmentMapper->findCountByCardIds($uncachedIds) as $cardId => $count) {
			$counts[$cardId] = $count;
		}

		// Add counts from custom attachment services (e.g. files shared via FilesAppService)
		foreach (array_keys($this->services) as $attachmentType) {
			$service = $this->getService($attachmentType);
			if ($service instanceof ICustomAttachmentService) {
				foreach ($service->getAttachmentCountForCards($uncachedIds) as $cardId => $count) {
					$counts[$cardId] = ($counts[$cardId] ?? 0) + $count;
				}
			}
		}

		foreach ($uncachedIds as $cardId) {
			$this->attachmentCacheHelper->setAttachmentCount($cardId, $counts[$cardId]);
		}

		return $counts;
	}

	/**
	 * @return Attachment|\OCP\AppFramework\Db\Entity
	 * @throws NoPermissionException
	 * @throws StatusException
	 * @throws BadRequestException
	 */
	public function create(int $cardId, string $type, string $data = '') {
		$this->attachmentServiceValidator->check(compact('cardId', 'type'));

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);

		$this->attachmentCacheHelper->clearAttachmentCount($cardId);
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
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	public function display(int $cardId, int $attachmentId, string $type = 'deck_file'): Response {
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
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 */
	public function update(int $cardId, int $attachmentId, string $data, string $type = 'deck_file'): Attachment {
		$this->attachmentServiceValidator->check(compact('cardId', 'type', 'data'));

		try {
			$service = $this->getService($type);
		} catch (InvalidAttachmentType $e) {
			throw new NotFoundException();
		}

		if ($service instanceof ICustomAttachmentService) {
			$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
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
