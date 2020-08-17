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
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\BoardService;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class DeckProvider implements IProvider {

	/**
	 * @var BoardService
	 */
	private $boardService;
	/**
	 * @var CardMapper
	 */
	private $cardMapper;
	/**
	 * @var StackMapper
	 */
	private $stackMapper;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	public function __construct(
		BoardService $boardService,
		StackMapper $stackMapper,
		CardMapper $cardMapper,
		IURLGenerator $urlGenerator
	) {
		$this->boardService = $boardService;
		$this->stackMapper = $stackMapper;
		$this->cardMapper = $cardMapper;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'deck';
	}

	public function getName(): string {
		return 'Deck';
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$boards = $this->boardService->findAll();
		$cards = $this->cardMapper->search(array_map(function (Board $board) {
			return $board->getId();
		}, $boards), $query->getTerm(), $query->getLimit(), $query->getCursor());

		$self = $this;
		$results = array_merge(
			array_map(function (Board $board) {
				return new BoardSearchResultEntry($board, $this->urlGenerator);
			}, array_filter($boards, function($board) use ($query) {
				return mb_strpos($board->getTitle(), $query->getTerm()) !== -1;
			})),
			array_map(function (Card $card) use ($self) {
				$board = $self->boardService->find($self->cardMapper->findBoardId($card->getId()));
				$stack = $self->stackMapper->find($card->getStackId());
				return new CardSearchResultEntry($board, $stack, $card, $this->urlGenerator);
			}, $cards)
		);

		return SearchResult::complete(
			'Deck',
			$results
		);
	}

	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'deck.page.index') {
			// Before comments
			return -5;
		}
		return 10;
	}
}
