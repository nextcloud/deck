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
use OCP\Teams\ITeamAppProvider;
use OCP\Teams\TeamAppAction;
use OCP\Teams\TeamAppActionPrompt;
use OCP\Teams\TeamAppActionResult;
use OCP\Teams\TeamAppEntry;
use OCP\Teams\TeamAppNavigation;
use OCP\Teams\TeamAppResourceKey;
use OCP\Teams\TeamAppRouteAlias;

class TeamsProvider implements ITeamAppProvider {
	public function __construct(
		private BoardService $boards,
		private BoardMapper $boardMapper,
		private IL10N $l10n,
	) {
	}

	public function getId(): string {
		return 'deck';
	}

	public function load(): ?string {
		return null;
	}

	public function getOrder(): int {
		return 20;
	}

	/** @return list<TeamAppRouteAlias> */
	public function getRouteAliases(): array {
		return [];
	}

	public function getNavigation(array $context): TeamAppNavigation {
		$boards = [];
		foreach ($this->boardMapper->findAllAttachedToTeam($context['teamId']) as $board) {
			if (!$board->getArchived() && !$board->getDeletedAt()) {
				$boards[(string)$board->getId()] = $board;
			}
		}
		$entries = [];
		$keys = [];
		foreach ($context['resources'] as $resource) {
			if ($resource->getProvider()->getId() !== 'deck' || !isset($boards[$resource->getId()])) {
				continue;
			}
			$board = $boards[$resource->getId()];
			$entries[] = new TeamAppEntry(
				id: (string)$board->getId(), label: $board->getTitle(),
				href: $resource->getUrl(), iconSvg: $resource->getIconSvg(),
				legacyIds: ['board-' . $board->getId()],
			);
			$keys[] = new TeamAppResourceKey('deck', $resource->getId());
		}
		usort($entries, static fn (TeamAppEntry $left, TeamAppEntry $right): int => strnatcasecmp($left->label, $right->label));
		return new TeamAppNavigation(entries: $entries, resourceKeys: $keys, actions: $context['isAdmin'] ? [new TeamAppAction(
			id: 'deck-create', label: $this->l10n->t('New Deck board'),
			prompt: new TeamAppActionPrompt($this->l10n->t('Board name')),
		)] : []);
	}

	public function executeAction(array $context, string $actionId, array $parameters): TeamAppActionResult {
		if (!$context['isAdmin'] || $actionId !== 'deck-create') {
			throw new InvalidArgumentException('Unknown or unauthorized Teams action');
		}
		$title = $parameters['value'] ?? null;
		if (!is_string($title) || trim($title) === '') {
			throw new InvalidArgumentException('Board name must not be empty');
		}
		$this->boards->createForTeam(trim($title), $context['userId'], null, $context['teamId']);
		return new TeamAppActionResult();
	}
}
