<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Search;

use OCA\Deck\Search\Query\DateQueryParameter;
use OCA\Deck\Search\Query\SearchQuery;
use OCA\Deck\Search\Query\StringQueryParameter;
use OCP\IL10N;

class FilterStringParser {

	/**
	 * @var IL10N
	 */
	private $l10n;

	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}

	public function parse(?string $filter): SearchQuery {
		$query = new SearchQuery();
		if (empty($filter)) {
			return $query;
		}
		/**
		 * Match search tokens that are separated by spaces
		 * do not match spaces that are surrounded by single or double quotes
		 * in order to still match quotes
		 * e.g.:
		 * - test
		 * - test:query
		 * - test:<123
		 * - test:"1 2 3"
		 * - test:>="2020-01-01"
		 */
		$searchQueryExpression = '/((\w+:(<|<=|>|>=)?)?("([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\')|[^\s]+)/';
		preg_match_all($searchQueryExpression, $filter, $matches, PREG_SET_ORDER, 0);
		foreach ($matches as $match) {
			$token = $match[0];
			if (!$this->parseFilterToken($query, $token)) {
				$query->addTextToken($this->removeQuotes($token));
			}
		}

		return $query;
	}

	private function parseFilterToken(SearchQuery $query, string $token): bool {
		if (!str_contains($token, ':')) {
			return false;
		}

		[$type, $param] = explode(':', $token, 2);
		$type = strtolower($type);

		$qualifier = null;

		switch ($type) {
			case 'date':
				$comparator = SearchQuery::COMPARATOR_EQUAL;
				$value = $param;
				if ($param[0] === '<' || $param[0] === '>') {
					$orEquals = $param[1] === '=';
					$value = $orEquals ? substr($param, 2) : substr($param, 1);
					$comparator = (
						($param[0] === '<' ? SearchQuery::COMPARATOR_LESS : 0) |
						($param[0] === '>' ? SearchQuery::COMPARATOR_MORE : 0) |
						($orEquals ? SearchQuery::COMPARATOR_EQUAL : 0)
					);
				}
				$query->addDuedate(new DateQueryParameter('date', $comparator, $this->removeQuotes($value)));
				return true;
			case 'title':
				$query->addTitle(new StringQueryParameter('title', SearchQuery::COMPARATOR_EQUAL, $this->removeQuotes($param)));
				return true;
			case 'description':
				$query->addDescription(new StringQueryParameter('description', SearchQuery::COMPARATOR_EQUAL, $this->removeQuotes($param)));
				return true;
			case 'list':
				$query->addStack(new StringQueryParameter('list', SearchQuery::COMPARATOR_EQUAL, $this->removeQuotes($param)));
				return true;
			case 'tag':
				$query->addTag(new StringQueryParameter('tag', SearchQuery::COMPARATOR_EQUAL, $this->removeQuotes($param)));
				return true;
			case 'assigned':
				$query->addAssigned(new StringQueryParameter('assigned', SearchQuery::COMPARATOR_EQUAL, $this->removeQuotes($param)));
				return true;
		}

		return false;
	}

	protected function removeQuotes(string $token): string {
		if (mb_strlen($token) > 1) {
			$token = ($token[0] === '"' && $token[mb_strlen($token) - 1] === '"') ? mb_substr($token, 1, -1) : $token;
			$token = ($token[0] === '\'' && $token[mb_strlen($token) - 1] === '\'') ? mb_substr($token, 1, -1) : $token;
		}
		return $token;
	}
}
