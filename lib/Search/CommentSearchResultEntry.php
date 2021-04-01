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

use OCA\Deck\Db\Card;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Search\SearchResultEntry;

class CommentSearchResultEntry extends SearchResultEntry {
	private $commentId;

	public function __construct(string $commentId, string $commentMessage, string $commentAuthor, Card $card, IURLGenerator $urlGenerator, IL10N $l10n) {
		parent::__construct(
			'',
			// TRANSLATORS This is describing the author and card title related to a comment e.g. "Jane on MyTask"
			$l10n->t('%s on %s', [$commentAuthor, $card->getTitle()]),
			$commentMessage,
			$urlGenerator->linkToRouteAbsolute('deck.page.index') . '#/board/' . $card->getRelatedBoard()->getId() . '/card/' . $card->getId() . '/comments/' . $commentId, // $commentId
			'icon-comment');
		$this->commentId = $commentId;
	}

	public function getCommentId(): string {
		return $this->commentId;
	}
}
