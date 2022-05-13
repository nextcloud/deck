<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Collaboration\Resources;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Server;

class ResourceProviderCard implements IProvider {
	public const RESOURCE_TYPE = 'deck-card';

	private CardMapper $cardMapper;
	private BoardMapper $boardMapper;
	private PermissionService $permissionService;
	private IURLGenerator $urlGenerator;
	protected array $nodes = [];

	public function __construct(CardMapper $cardMapper, BoardMapper $boardMapper, PermissionService $permissionService, IURLGenerator $urlGenerator) {
		$this->cardMapper = $cardMapper;
		$this->boardMapper = $boardMapper;
		$this->permissionService = $permissionService;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Get the type of a resource
	 *
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
	 * @since 16.0.0
	 */
	public function getResourceRichObject(IResource $resource): array {
		try {
			$card = $this->cardMapper->find($resource->getId());
			$board = $this->getBoard($resource->getId());
		} catch (DoesNotExistException $e) {
			throw new ResourceException('No card found for resource');
		} catch (MultipleObjectsReturnedException $e) {
			throw new ResourceException('No unique card found for resource, this should never happen');
		}

		$link = $this->urlGenerator->linkToRoute('deck.page.index') . '#/board/' . $board->getId() . '/card/' . $resource->getId();

		return [
			'type' => self::RESOURCE_TYPE,
			'id' => $resource->getId(),
			'name' => $board->getTitle() . ': ' . $card->getTitle(),
			'link' => $link,
			'iconUrl' => $this->urlGenerator->imagePath('core', 'actions/toggle-pictures.svg')
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
	 * @param $cardId
	 * @return Board
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function getBoard($cardId) {
		$boardId = $this->cardMapper->findBoardId($cardId);
		return $this->boardMapper->find($boardId, false, true);
	}

	public function invalidateAccessCache($cardId = null) {
		try {
			/** @var IManager $resourceManager */
			$resourceManager = Server::get(IManager::class);
		} catch (QueryException $e) {
		}
		if ($cardId !== null) {
			$resource = $resourceManager->getResourceForUser(self::RESOURCE_TYPE, (string)$cardId, null);
			$resourceManager->invalidateAccessCacheForResource($resource);
		} else {
			$resourceManager->invalidateAccessCacheForProvider($this);
		}
	}
}
