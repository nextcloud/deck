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
		$link = $this->urlGenerator->linkToRoute('deck.page.index') . '#/board/' . $resource->getId();

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
			return $this->boardMapper->find($resource->getId(), false, true);
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
