<?php

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Collaboration\Resources;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Server;

class ResourceProvider implements IProvider {
	public const RESOURCE_TYPE = 'deck';

	private BoardMapper $boardMapper;
	private PermissionService $permissionService;
	private IURLGenerator $urlGenerator;

	protected array $nodes = [];

	public function __construct(BoardMapper $boardMapper, PermissionService $permissionService, IURLGenerator $urlGenerator) {
		$this->boardMapper = $boardMapper;
		$this->permissionService = $permissionService;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Get the type of a resource
	 *
	 * @param IResource $resource
	 * @return string
	 * @since 15.0.0
	 */
	public function getType(): string {
		return self::RESOURCE_TYPE;
	}

	/**
	 * Get the rich object data of a resource
	 *
	 * @param IResource $resource
	 * @return array
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @since 16.0.0
	 */
	public function getResourceRichObject(IResource $resource): array {
		$board = $this->getBoard($resource);
		$link = $this->urlGenerator->linkToRoute('deck.page.indexBoard', ['boardId' => $resource->getId()]);

		return [
			'type' => self::RESOURCE_TYPE,
			'id' => $resource->getId(),
			'name' => $board->getTitle(),
			'link' => $link,
			'iconUrl' => $this->urlGenerator->imagePath('deck', 'deck-dark.svg')
		];
	}

	/**
	 * Can a user/guest access the collection
	 *
	 * @param IResource $resource
	 * @param IUser|null $user
	 * @return bool
	 * @since 16.0.0
	 */
	public function canAccessResource(IResource $resource, ?IUser $user): bool {
		if ($resource->getType() !== self::RESOURCE_TYPE || !$user instanceof IUser) {
			return false;
		}
		$board = $this->getBoard($resource);
		if ($board === null) {
			return false;
		}
		if ($board->getOwner() === $user->getUID()) {
			return true;
		}
		if ($board->getAcl() === null) {
			return false;
		}
		return $this->permissionService->userCan($board->getAcl(), Acl::PERMISSION_READ, $user->getUID());
	}

	private function getBoard(IResource $resource) {
		try {
			return $this->boardMapper->find((int)$resource->getId(), false, true);
		} catch (DoesNotExistException $e) {
		} catch (MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	public function invalidateAccessCache($boardId = null) {
		try {
			/** @var IManager $resourceManager */
			$resourceManager = Server::get(IManager::class);
		} catch (QueryException $e) {
		}
		if ($boardId !== null) {
			$resource = $resourceManager->getResourceForUser(self::RESOURCE_TYPE, (string)$boardId, null);
			$resourceManager->invalidateAccessCacheForResource($resource);
		} else {
			$resourceManager->invalidateAccessCacheForProvider($this);
		}
	}
}
