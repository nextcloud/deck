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

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Service\SearchService;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class DeckProvider implements IProvider {

	/**
	 * @var SearchService
	 */
	private $searchService;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	public function __construct(
		SearchService $searchService,
		IURLGenerator $urlGenerator
	) {
		$this->searchService = $searchService;
		$this->urlGenerator = $urlGenerator;
	}

	public function getId(): string {
		return 'deck';
	}

	public function getName(): string {
		return 'Deck';
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$cursor = $query->getCursor() !== null ? (int)$query->getCursor() : null;
		$boardResults = $this->searchService->searchBoards($query->getTerm(), $query->getLimit(), $cursor);
		$cardResults = $this->searchService->searchCards($query->getTerm(), $query->getLimit(), $cursor);
		$results = array_merge(
			array_map(function (Board $board) {
				return new BoardSearchResultEntry($board, $this->urlGenerator);
			}, $boardResults),
			array_map(function (Card $card) {
				return new CardSearchResultEntry($card->getRelatedBoard(), $card->getRelatedStack(), $card, $this->urlGenerator);
			}, $cardResults)
		);

		if (count($cardResults) < $query->getLimit()) {
			return SearchResult::complete(
				'Deck',
				$results
			);
		}
		
		return SearchResult::paginated(
			'Deck',
			$results,
			$cardResults[count($results) - 1]->getLastModified()
		);
	}

	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'deck.Page.index') {
			return -5;
		}
		return 10;
	}
}
