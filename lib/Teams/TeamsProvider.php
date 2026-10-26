<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Teams;

use InvalidArgumentException;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Teams\ITeamAppProvider;
use OCP\Teams\ITeamManager;
use OCP\Teams\TeamAppAction;
use OCP\Teams\TeamAppActionPrompt;
use OCP\Teams\TeamAppActionResult;
use OCP\Teams\TeamAppEntry;
use OCP\Teams\TeamAppNavigation;

class TeamsProvider implements ITeamAppProvider {
	public function __construct(
		private BoardService $boards,
		private BoardMapper $boardMapper,
		private ITeamManager $teamManager,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getId(): string {
		return 'deck';
	}

	public function getOrder(): int {
		return 20;
	}

	public function getNavigation(string $teamId, string $userId, bool $isAdmin, ?\OCP\Teams\TeamFolder $folder): TeamAppNavigation {
		$boards = [];
		foreach ($this->boardMapper->findAllAttachedToTeam($teamId) as $board) {
			if (!$board->getArchived() && !$board->getDeletedAt()) {
				$boards[(string)$board->getId()] = $board;
			}
		}
		$sharedBoardIds = array_fill_keys(array_map(
			static fn ($resource): string => $resource->getId(),
			array_filter($this->teamManager->getSharedWith($teamId, $userId), static fn ($resource): bool => $resource->getProvider()->getId() === 'deck'),
		), true);
		$entries = [];
		foreach ($sharedBoardIds as $resourceId => $_) {
			if (!isset($boards[$resourceId])) {
				continue;
			}
			$board = $boards[$resourceId];
			$entries[] = new TeamAppEntry(
				id: (string)$board->getId(), label: $board->getTitle(),
				icon: 'M16,5V18H21V5M4,18H9V5H4M10,18H15V5H10V18Z',
				href: $this->urlGenerator->getAbsoluteURL('/apps/deck/#/board/' . $board->getId()),
				referenceUrl: $this->urlGenerator->getAbsoluteURL('/apps/deck/#/board/' . $board->getId()),
			);
		}
		usort($entries, static fn (TeamAppEntry $left, TeamAppEntry $right): int => strnatcasecmp($left->label, $right->label));
		return new TeamAppNavigation(entries: $entries, createAction: $isAdmin ? new TeamAppAction(
			id: 'deck-create', label: $this->l10n->t('New Deck board'),
			dialog: new TeamAppActionPrompt($this->l10n->t('Board name')),
		) : null);
	}

	public function executeAction(string $teamId, string $teamName, string $userId, bool $isAdmin, ?\OCP\Teams\TeamFolder $folder, string $actionId, array $parameters): TeamAppActionResult {
		if (!$isAdmin || $actionId !== 'deck-create') {
			throw new InvalidArgumentException('Unknown or unauthorized Teams action');
		}
		$title = $parameters['value'] ?? null;
		if (!is_string($title) || trim($title) === '') {
			throw new InvalidArgumentException('Board name must not be empty');
		}
		$this->boards->createForTeam(trim($title), $userId, null, $teamId);
		return new TeamAppActionResult();
	}
}
