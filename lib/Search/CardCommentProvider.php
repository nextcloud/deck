<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Search;

use OCA\Deck\Service\SearchService;
use OCP\IL10N;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class CardCommentProvider implements IProvider {

	/** @var SearchService */
	private $searchService;
	/** @var IL10N */
	private $l10n;

	public function __construct(
		SearchService $searchService,
		IL10N $l10n,
	) {
		$this->searchService = $searchService;
		$this->l10n = $l10n;
	}

	public function getId(): string {
		return 'search-deck-comment';
	}

	public function getName(): string {
		return $this->l10n->t('Card comments');
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$cursor = $query->getCursor() !== null ? (int)$query->getCursor() : null;
		$results = $this->searchService->searchComments($query->getTerm(), $query->getLimit(), $cursor);
		if (count($results) < $query->getLimit()) {
			return SearchResult::complete(
				$this->l10n->t('Card comments'),
				$results
			);
		}

		return SearchResult::paginated(
			$this->l10n->t('Card comments'),
			$results,
			$results[count($results) - 1]->getCommentId()
		);
	}

	public function getOrder(string $route, array $routeParameters): int {
		// Negative value to force showing deck providers on first position if the app is opened
		// This provider always has an order 1 higher than the default DeckProvider
		if ($route === 'deck.Page.index') {
			return -4;
		}
		return 11;
	}
}
