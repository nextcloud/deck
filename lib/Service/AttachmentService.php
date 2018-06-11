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


use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\NotFoundException;
use OCP\AppFramework\Http\Response;

class AttachmentService {

	private $attachmentMapper;
	private $cardMapper;
	private $permissionService;
	private $userId;

	/** @var IAttachmentService[] */
	private $services = [];
	private $application;

	/**
	 * AttachmentService constructor.
	 *
	 * @param AttachmentMapper $attachmentMapper
	 * @param CardMapper $cardMapper
	 * @param PermissionService $permissionService
	 * @param $userId
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function __construct(AttachmentMapper $attachmentMapper, CardMapper $cardMapper, PermissionService $permissionService, Application $application, $userId) {
		$this->attachmentMapper = $attachmentMapper;
		$this->cardMapper = $cardMapper;
		$this->permissionService = $permissionService;
		$this->userId = $userId;
		$this->application = $application;

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
	 */
	public function findAll($cardId) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		$attachments = $this->attachmentMapper->findAll($cardId);
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

	public function create($cardId, $type, $data) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);

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
		$attachment = $this->attachmentMapper->insert($attachment);

		// extend data so the frontend can use it properly after creating
		try {
			$service = $this->getService($attachment->getType());
			$service->extendData($attachment);
		} catch (InvalidAttachmentType $e) {
			// just store the data
		}
		return $attachment;
	}


	/**
	 * Display the attachment
	 *
	 * @param $cardId
	 * @param $attachmentId
	 * @return Response
	 * @throws NotFoundException
	 */
	public function display($cardId, $attachmentId) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		$attachment = $this->attachmentMapper->find($attachmentId);

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
	 * @param $cardId
	 * @param $attachmentId
	 * @param $request
	 * @return mixed
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function update($cardId, $attachmentId, $data) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$attachment = $this->attachmentMapper->find($attachmentId);
		$attachment->setData($data);
		try {
			$service = $this->getService($attachment->getType());
			$service->update($attachment);
		} catch (InvalidAttachmentType $e) {
			// just update without further action
		}
		return $attachment;
	}

	public function delete($cardId, $attachmentId) {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);

		$attachment = $this->attachmentMapper->find($attachmentId);
		try {
			$service = $this->getService($attachment->getType());
			$service->delete($attachment);
		} catch (InvalidAttachmentType $e) {
			// just delete without further action
		}
		$this->attachmentMapper->delete($attachment);
		return $attachment;
	}
}