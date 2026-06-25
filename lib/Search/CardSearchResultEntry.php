<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Search;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
use OCP\Search\SearchResultEntry;

class CardSearchResultEntry extends SearchResultEntry {
	public function __construct(Board $board, Stack $stack, Card $card, $urlGenerator) {
		parent::__construct(
			$urlGenerator->getAbsoluteURL(
				$urlGenerator->imagePath(Application::APP_ID, 'card.svg')
			),
			$card->getTitle(),
			$board->getTitle() . ' » ' . $stack->getTitle(),
			$urlGenerator->linkToRouteAbsolute('deck.page.redirectToCard', ['cardId' => $card->getId()]),
			'icon-deck'
		);
	}
}
