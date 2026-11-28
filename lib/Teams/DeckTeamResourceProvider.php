<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Teams;

use InvalidArgumentException;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCP\IURLGenerator;
use OCP\Teams\ITeamManager;
use OCP\Teams\ITeamOwnedResourceProvider;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamResource;

class DeckTeamResourceProvider implements ITeamResourceProvider, ITeamOwnedResourceProvider {
	public function __construct(
		private BoardService $boards,
		private BoardMapper $boardMapper,
		private ITeamManager $teamManager,
		private IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getId(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getName(): string {
		return 'Deck';
	}

	#[\Override]
	public function getIconSvg(): string {
		return file_get_contents(__DIR__ . '/../../img/deck-current.svg');
	}

	#[\Override]
	public function getSharedWith($teamId): array {
		$boards = $this->boardMapper->findAllByTeam($teamId);
		return array_map(function (Board $board) {
			return new TeamResource(
				$this,
				(string)$board->getId(),
				$board->getTitle(),
				$this->urlGenerator->linkToRouteAbsolute('deck.page.indexBoard', ['boardId' => $board->getId()]),
				$this->getBoardBulletIcon($board),
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('deck', 'deck-current.svg')),
			);
		}, $boards);
	}

	#[\Override]
	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		return $this->boardMapper->isSharedWithTeam((int)$resourceId, $teamId);
	}

	#[\Override]
	public function getTeamsForResource(string $resourceId): array {
		return $this->boardMapper->findTeamsForBoard((int)$resourceId);
	}

	public function getBoardBulletIcon(Board $board): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="16" width="16" version="1.1" viewBox="0 0 16 16"><g fill="#' . $board->getColor() . '"><rect ry="15" height="14" width="14" y="1" x="1"/></g></svg>';
	}

	public function getOwnedByTeam(string $teamId, string $userId, bool $isAdmin, ?\OCP\Teams\TeamFolder $folder): array {
		$boards = [];
		foreach ($this->boardMapper->findAllAttachedToTeam($teamId) as $board) {
			if (!$board->getArchived() && !$board->getDeletedAt()) {
				$boards[(string)$board->getId()] = $board;
			}
		}
		$entries = [];
		foreach ($this->teamManager->getSharedWith($teamId, $userId) as $resource) {
			$resourceId = $resource->getId();
			if ($resource->getProvider()->getId() !== 'deck' || !isset($boards[$resourceId])) {
				continue;
			}
			$board = $boards[$resourceId];
			$url = $this->urlGenerator->linkToRouteAbsolute('deck.page.indexBoard', ['boardId' => $resourceId]);
			$referenceUrl = $this->urlGenerator->getAbsoluteURL('/apps/deck/#/board/' . $resourceId);
			$entries[$resourceId] = new TeamResource(
				$resource->getProvider(),
				$resourceId,
				$board->getTitle(),
				$url,
				iconSvg: $resource->getIconSvg(),
				iconURL: $resource->getIconURL(),
				iconEmoji: $resource->getIconEmoji(),
				referenceUrl: $referenceUrl,
			);
		}
		usort($entries, static fn (TeamResource $left, TeamResource $right): int => strnatcasecmp($left->getLabel(), $right->getLabel()));
		return $entries;
	}

	public function canCreateResource(string $teamId, string $userId, bool $isAdmin, ?\OCP\Teams\TeamFolder $folder): bool {
		return $isAdmin;
	}

	public function createResource(string $teamId, string $teamName, string $userId, bool $isAdmin, ?\OCP\Teams\TeamFolder $folder): string {
		if (!$this->canCreateResource($teamId, $userId, $isAdmin, $folder)) {
			throw new InvalidArgumentException('Unknown or unauthorized Teams action');
		}
		$title = $teamName;
		if (trim($title) === '') {
			throw new InvalidArgumentException('Board name must not be empty');
		}
		$board = $this->boards->createForTeam(trim($title), $userId, null, $teamId);
		return (string)$board->getId();
	}
}
