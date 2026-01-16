<?php

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Collaboration\Resources;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Server;
use Override;
use Psr\Container\ContainerExceptionInterface;

class ResourceProvider implements IProvider {
	public const RESOURCE_TYPE = 'deck';

	protected array $nodes = [];

	public function __construct(
		private readonly BoardMapper $boardMapper,
		private readonly PermissionService $permissionService,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	#[Override]
	public function getType(): string {
		return self::RESOURCE_TYPE;
	}

	#[Override]
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

	#[Override]
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

	private function getBoard(IResource $resource): ?Board {
		try {
			return $this->boardMapper->find((int)$resource->getId(), false, true);
		} catch (DoesNotExistException $e) {
		} catch (MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	public function invalidateAccessCache(?int $boardId = null): void {
		try {
			/** @var IManager $resourceManager */
			$resourceManager = Server::get(IManager::class);
		} catch (ContainerExceptionInterface $e) {
		}
		if ($boardId !== null) {
			$resource = $resourceManager->getResourceForUser(self::RESOURCE_TYPE, (string)$boardId, null);
			$resourceManager->invalidateAccessCacheForResource($resource);
		} else {
			$resourceManager->invalidateAccessCacheForProvider($this);
		}
	}
}
