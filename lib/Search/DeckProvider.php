<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Search;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Service\SearchService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class DeckProvider implements IProvider {
	private IL10N $l10n;
	private SearchService $searchService;
	private IURLGenerator $urlGenerator;

	public function __construct(
		SearchService $searchService,
		IURLGenerator $urlGenerator,
		IL10N $l10n,
	) {
		$this->l10n = $l10n;
		$this->searchService = $searchService;
		$this->urlGenerator = $urlGenerator;
	}

	public function getId(): string {
		return 'search-deck-card-board';
	}

	public function getName(): string {
		return $this->l10n->t('Deck boards and cards');
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$cursor = $query->getCursor();
		[$boardCursor, $cardCursor] = $this->parseCursor($cursor);

		$boardObjects = $this->searchService->searchBoards($query->getTerm(), $query->getLimit(), $boardCursor);
		$boardResults = array_map(function (Board $board) {
			return [
				'object' => $board,
				'entry' => new BoardSearchResultEntry($board, $this->urlGenerator)
			];
		}, $boardObjects);

		$cardObjects = $this->searchService->searchCards($query->getTerm(), $query->getLimit(), $cardCursor);
		$cardResults = array_map(function (Card $card) {
			return [
				'object' => $card,
				'entry' => new CardSearchResultEntry($card->getRelatedBoard(), $card->getRelatedStack(), $card, $this->urlGenerator)
			];
		}, $cardObjects);

		$results = array_merge($boardResults, $cardResults);

		usort($results, function ($a, $b) {
			$ta = $a['object']->getLastModified();
			$tb = $b['object']->getLastModified();
			return $ta === $tb
				? 0
				: ($ta > $tb ? -1 : 1);
		});

		$resultEntries = array_map(function (array $result) {
			return $result['entry'];
		}, $results);

		// if both cards and boards results are less then the limit, we know we won't get more
		if (count($resultEntries) < $query->getLimit()) {
			return SearchResult::complete(
				$this->getName(),
				$resultEntries
			);
		}

		$newCursor = $this->getNewCursor($boardObjects, $cardObjects);
		return SearchResult::paginated(
			$this->getName(),
			$resultEntries,
			$newCursor
		);
	}

	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'deck.Page.index') {
			return -5;
		}
		return 10;
	}

	private function parseCursor(?string $cursor): array {
		$boardCursor = null;
		$cardCursor = null;
		if ($cursor !== null) {
			$splitCursor = explode('|', $cursor);
			if (count($splitCursor) >= 2) {
				$boardCursor = (int)$splitCursor[0] ?: null;
				$cardCursor = (int)$splitCursor[1] ?: null;
			}
		}
		return [$boardCursor, $cardCursor];
	}

	private function getNewCursor(array $boards, array $cards): string {
		$boardTimestamps = array_map(function (Board $board) {
			return $board->getLastModified();
		}, $boards);
		$cardTimestamps = array_map(function (Card $card) {
			return $card->getLastModified();
		}, $cards);
		return (count($boardTimestamps) > 0 ? min($boardTimestamps) : '') . '|' . (count($cardTimestamps) > 0 ? min($cardTimestamps) : '');
	}
}
