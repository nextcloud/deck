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

namespace OCA\Deck\Search;

use OCA\Deck\Search\Query\DateQueryParameter;
use OCA\Deck\Search\Query\SearchQuery;
use OCA\Deck\Search\Query\StringQueryParameter;
use OCP\IL10N;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class FilterStringParserTest extends TestCase {
	private $l10n;
	private $parser;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->parser = new FilterStringParser($this->l10n);
	}

	public function testParseEmpty() {
		$result = $this->parser->parse(null);
		$expected = new SearchQuery();
		Assert::assertEquals($expected, $result);
	}

	public function testParseTextTokens() {
		$result = $this->parser->parse('a b c');
		$expected = new SearchQuery();
		$expected->addTextToken('a');
		$expected->addTextToken('b');
		$expected->addTextToken('c');
		Assert::assertEquals($expected, $result);
	}

	public function testParseTextToken() {
		$result = $this->parser->parse('abc');
		$expected = new SearchQuery();
		$expected->addTextToken('abc');
		Assert::assertEquals($expected, $result);
	}

	public function testParseTextTokenQuotes() {
		$result = $this->parser->parse('a b c "a b c" tag:abc tag:"a b c" tag:\'d e f\'');
		$expected = new SearchQuery();
		$expected->addTextToken('a');
		$expected->addTextToken('b');
		$expected->addTextToken('c');
		$expected->addTextToken('a b c');
		$expected->addTag(new StringQueryParameter('tag', SearchQuery::COMPARATOR_EQUAL, 'abc'));
		$expected->addTag(new StringQueryParameter('tag', SearchQuery::COMPARATOR_EQUAL, 'a b c'));
		$expected->addTag(new StringQueryParameter('tag', SearchQuery::COMPARATOR_EQUAL, 'd e f'));
		Assert::assertEquals($expected, $result);
	}

	public function testParseTagComparatorNotSupported() {
		$result = $this->parser->parse('tag:<"a tag"');
		$expected = new SearchQuery();
		$expected->addTag(new StringQueryParameter('tag', SearchQuery::COMPARATOR_EQUAL, '<"a tag"'));
		Assert::assertEquals($expected, $result);
	}

	public function testParseTextTokenQuotesSingle() {
		$result = $this->parser->parse('a b c \'a b c\'');
		$expected = new SearchQuery();
		$expected->addTextToken('a');
		$expected->addTextToken('b');
		$expected->addTextToken('c');
		$expected->addTextToken('a b c');
		Assert::assertEquals($expected, $result);
	}

	public function testParseTextTokenQuotesWrong() {
		$result = $this->parser->parse('"a b" c"');
		$expected = new SearchQuery();
		$expected->addTextToken('a b');
		$expected->addTextToken('c"');
		Assert::assertEquals($expected, $result);
	}

	public function dataParseDate() {
		return [
			['date:today', [new DateQueryParameter('date', SearchQuery::COMPARATOR_EQUAL, 'today')], []],
			['date:>today', [new DateQueryParameter('date', SearchQuery::COMPARATOR_MORE, 'today')], []],
			['date:>=today', [new DateQueryParameter('date', SearchQuery::COMPARATOR_MORE_EQUAL, 'today')], []],
			['date:<today', [new DateQueryParameter('date', SearchQuery::COMPARATOR_LESS, 'today')], []],
			['date:<=today', [new DateQueryParameter('date', SearchQuery::COMPARATOR_LESS_EQUAL, 'today')], []],
			['date:<+today', [new DateQueryParameter('date', SearchQuery::COMPARATOR_LESS, '+today')], []],
			['date:<>today', [new DateQueryParameter('date', SearchQuery::COMPARATOR_LESS, '>today')], []],
			['date:=today', [new DateQueryParameter('date', SearchQuery::COMPARATOR_EQUAL, '=today')], []],
			['date:today todo', [new DateQueryParameter('date', SearchQuery::COMPARATOR_EQUAL, 'today')], ['todo']],
			['date:"last day of next month" todo', [new DateQueryParameter('date', SearchQuery::COMPARATOR_EQUAL, 'last day of next month')], ['todo']],
			['date:"last day of next month" "todo task" task', [new DateQueryParameter('date', SearchQuery::COMPARATOR_EQUAL, 'last day of next month')], ['todo task', 'task']],
		];
	}
	/**
	 * @dataProvider dataParseDate
	 */
	public function testParseDate($query, $dates, array $tokens) {
		$result = $this->parser->parse($query);
		$expected = new SearchQuery();
		foreach ($dates as $date) {
			$expected->addDuedate($date);
		}
		foreach ($tokens as $token) {
			$expected->addTextToken($token);
		}
		Assert::assertEquals($expected, $result);
	}
}
