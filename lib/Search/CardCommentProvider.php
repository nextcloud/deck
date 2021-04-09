<?php
/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
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
 *
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
		IL10N $l10n
	) {
		$this->searchService = $searchService;
		$this->l10n = $l10n;
	}

	public function getId(): string {
		return 'deck-comment';
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
