<?php

declare(strict_types=1);

namespace OCA\Deck\Teams;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCP\IURLGenerator;
use OCP\Teams\TeamResource;

/**
 * @copyright Copyright (c) 2024 Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class DeckTeamResourceProvider implements \OCP\Teams\ITeamResourceProvider {

	public function __construct(
		private BoardMapper $boardMapper,
		private IURLGenerator $urlGenerator,
	) {
	}


	public function getId(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return 'Deck';
	}

	public function getIconSvg(): string {
		return file_get_contents(__DIR__ . '/../../img/deck-current.svg');
	}

	public function getSharedWith($teamId): array {
		$boards = $this->boardMapper->findAllByTeam($teamId);
		return array_map(function (Board $board) {
			return new TeamResource(
				$this,
				(string)$board->getId(),
				$board->getTitle(),
				$this->urlGenerator->linkToRouteAbsolute('deck.page.index') . '#/board/' . $board->getId(),
				$this->getBoardBulletIcon($board),
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('deck', 'deck-current.svg')),
			);
		}, $boards);
	}

	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		return $this->boardMapper->isSharedWithTeam((int)$resourceId, $teamId);
	}

	public function getTeamsForResource(string $resourceId): array {
		return $this->boardMapper->findTeamsForBoard((int)$resourceId);
	}

	public function getBoardBulletIcon(Board $board): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="16" width="16" version="1.1" viewBox="0 0 16 16"><g fill="#' . $board->getColor(). '"><rect ry="15" height="14" width="14" y="1" x="1"/></g></svg>';
	}
}
