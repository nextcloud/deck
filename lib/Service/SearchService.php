<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
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


namespace OCA\Deck\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Search\CommentSearchResultEntry;
use OCA\Deck\Search\FilterStringParser;
use OCP\Comments\ICommentsManager;

class SearchService {

	/** @var BoardService */
	private $boardService;
	/** @var CardMapper */
	private $cardMapper;
	/** @var CardService */
	private $cardService;
	/** @var ICommentsManager */
	private $commentsManager;
	/** @var FilterStringParser */
	private $filterStringParser;

	public function __construct(
		BoardService $boardService,
		CardMapper $cardMapper,
		CardService $cardService,
		ICommentsManager $commentsManager,
		FilterStringParser $filterStringParser
	) {
		$this->boardService = $boardService;
		$this->cardMapper = $cardMapper;
		$this->cardService = $cardService;
		$this->commentsManager = $commentsManager;
		$this->filterStringParser = $filterStringParser;
	}

	public function searchCards(string $term, int $limit = null, ?int $cursor = null): array {
		$boards = $this->boardService->getUserBoards();
		$boardIds = array_map(static function (Board $board) {
			return $board->getId();
		}, $boards);
		$matchedCards = $this->cardMapper->search($boardIds, $this->filterStringParser->parse($term), $limit, $cursor);
		
		$self = $this;
		return array_map(function (Card $card) use ($self) {
			$self->cardService->enrich($card);
			return $card;
		}, $matchedCards);
	}

	public function searchBoards(string $term, ?int $limit, ?int $cursor): array {
		$boards = $this->boardService->getUserBoards();
		return array_filter($boards, static function (Board $board) use ($term) {
			return mb_stripos(mb_strtolower($board->getTitle()), mb_strtolower($term)) > -1;
		});
	}

	public function searchComments(string $term, ?int $limit = null, ?int $cursor = null): array {
		$boards = $this->boardService->getUserBoards();
		$boardIds = array_map(static function (Board $board) {
			return $board->getId();
		}, $boards);
		$matchedComments = $this->cardMapper->searchComments($boardIds, $this->filterStringParser->parse($term), $limit, $cursor);

		$self = $this;
		return array_filter(array_map(function ($cardRow) use ($self) {
			$comment = $this->commentsManager->get($cardRow['comment_id']);
			unset($cardRow['comment_id']);
			$card = Card::fromRow($cardRow);
			$self->cardService->enrich($card);
			$user = $this->userManager->get($comment->getActorId());
			$displayName = $user ? $user->getDisplayName() : '';
			return new CommentSearchResultEntry($comment->getId(), $comment->getMessage(), $displayName, $card, $this->urlGenerator, $this->l10n);
		}, $matchedComments));
	}
}
