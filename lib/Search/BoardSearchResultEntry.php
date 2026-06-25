<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Search;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Board;
use OCP\Search\SearchResultEntry;

class BoardSearchResultEntry extends SearchResultEntry {
	public function __construct(Board $board, $urlGenerator) {
		parent::__construct(
			$urlGenerator->getAbsoluteURL(
				$urlGenerator->imagePath(Application::APP_ID, 'deck-dark.svg')
			),
			$board->getTitle(),
			'',
			$urlGenerator->linkToRouteAbsolute('deck.page.indexBoard', ['boardId' => $board->getId()]),
			'icon-deck');
	}
}
