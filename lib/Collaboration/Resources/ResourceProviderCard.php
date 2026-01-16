<?php

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Collaboration\Resources;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\IURLGenerator;
use OCP\IUser;
use Override;

class ResourceProviderCard implements IProvider {
	public const RESOURCE_TYPE = 'deck-card';

	protected array $nodes = [];

	public function __construct(
		private readonly CardMapper $cardMapper,
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
		try {
			$card = $this->cardMapper->find($resource->getId());
			$board = $this->getBoard($resource->getId());
		} catch (DoesNotExistException $e) {
			throw new ResourceException('No card found for resource');
		} catch (MultipleObjectsReturnedException $e) {
			throw new ResourceException('No unique card found for resource, this should never happen');
		}

		$link = $this->urlGenerator->linkToRoute('deck.page.indexCard', [
			'boardId' => $board->getId(),
			'cardId' => $card->getId()
		]);

		return [
			'type' => self::RESOURCE_TYPE,
			'id' => $resource->getId(),
			'name' => $board->getTitle() . ': ' . $card->getTitle(),
			'link' => $link,
			'iconUrl' => $this->urlGenerator->imagePath('core', 'actions/toggle-pictures.svg')
		];
	}

	#[Override]
	public function canAccessResource(IResource $resource, ?IUser $user): bool {
		if (!$user instanceof IUser || $resource->getType() !== self::RESOURCE_TYPE) {
			return false;
		}
		try {
			$board = $this->getBoard($resource->getId());
		} catch (DoesNotExistException $e) {
			return false;
		} catch (MultipleObjectsReturnedException $e) {
			return false;
		}

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

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function getBoard(int $cardId): Board {
		$boardId = $this->cardMapper->findBoardId($cardId);
		return $this->boardMapper->find($boardId, false, true);
	}
}
