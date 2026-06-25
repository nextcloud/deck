<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
			$urlGenerator->getAbsoluteURL(
				$urlGenerator->imagePath('core', 'actions/comment.svg')
			),
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
