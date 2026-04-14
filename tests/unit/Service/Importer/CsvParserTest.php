<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer;

use PHPUnit\Framework\TestCase;

class CsvParserTest extends TestCase {

	private CsvParser $parser;

	protected function setUp(): void {
		parent::setUp();
		$this->parser = new CsvParser();
	}

	public function testParseSimpleCsv(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"My Card\"\t\"A description\"\t\"To Do\"\t\"Feature, Bug,\"\t\"null\"\t\"01/02/2026\"\t\"15/03/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(1, $rows);
		$this->assertEquals('My Card', $rows[0]['title']);
		$this->assertEquals('A description', $rows[0]['description']);
		$this->assertEquals('To Do', $rows[0]['stackName']);
		$this->assertEquals(['Feature', 'Bug'], $rows[0]['tags']);
		$this->assertNull($rows[0]['duedate']);
		$this->assertInstanceOf(\DateTime::class, $rows[0]['createdAt']);
		$this->assertEquals('2026-02-01', $rows[0]['createdAt']->format('Y-m-d'));
		$this->assertInstanceOf(\DateTime::class, $rows[0]['lastModified']);
		$this->assertEquals('2026-03-15', $rows[0]['lastModified']->format('Y-m-d'));
	}

	public function testParseUtf16LeWithBom(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"Test\"\t\"\"\t\"Done\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$utf16 = mb_convert_encoding($csv, 'UTF-16LE', 'UTF-8');
		// Add BOM
		$utf16WithBom = "\xFF\xFE" . $utf16;

		$rows = $this->parser->parse($utf16WithBom);

		$this->assertCount(1, $rows);
		$this->assertEquals('Test', $rows[0]['title']);
		$this->assertEquals('Done', $rows[0]['stackName']);
	}

	public function testParseMultilineDescription(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"My Card\"\t\"Line 1\nLine 2\nLine 3\"\t\"To Do\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(1, $rows);
		$this->assertEquals("Line 1\nLine 2\nLine 3", $rows[0]['description']);
	}

	public function testParseEscapedQuotes(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"Card with \"\"quotes\"\"\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(1, $rows);
		$this->assertEquals('Card with "quotes"', $rows[0]['title']);
	}

	public function testParseDateFormats(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"Card 1\"\t\"\"\t\"To Do\"\t\"\"\t\"2026-02-20T19:00:00+00:00\"\t\"23/02/2026\"\t\"28/03/2026\"\r\n";
		$csv .= "\"Card 2\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(2, $rows);
		// ISO 8601 date
		$this->assertInstanceOf(\DateTime::class, $rows[0]['duedate']);
		$this->assertEquals('2026-02-20', $rows[0]['duedate']->format('Y-m-d'));
		// null date
		$this->assertNull($rows[1]['duedate']);
		// d/m/Y date
		$this->assertInstanceOf(\DateTime::class, $rows[0]['createdAt']);
		$this->assertEquals('2026-02-23', $rows[0]['createdAt']->format('Y-m-d'));
	}

	/**
	 * @dataProvider dateFormatProvider
	 */
	public function testParseDateVariousFormats(string $input, ?string $expectedDate): void {
		$result = $this->parser->parseDate($input);
		if ($expectedDate === null) {
			$this->assertNull($result, 'Expected null for input "' . $input . '", got ' . ($result ? $result->format('Y-m-d') : 'null'));
		} else {
			$this->assertInstanceOf(\DateTime::class, $result, 'Expected DateTime for input "' . $input . '"');
			$this->assertEquals($expectedDate, $result->format('Y-m-d'), 'Date mismatch for input "' . $input . '"');
		}
	}

	public static function dateFormatProvider(): array {
		return [
			// ISO 8601 with timezone
			'ISO 8601' => ['2026-02-20T19:00:00+00:00', '2026-02-20'],
			'ISO 8601 negative offset' => ['2026-07-15T10:30:00-05:00', '2026-07-15'],

			// ISO date without time (sv-SE, lt-LT locales)
			'ISO date' => ['2026-02-20', '2026-02-20'],
			'ISO date end of year' => ['2026-12-31', '2026-12-31'],

			// d/m/Y - en-GB, most of Europe
			'd/m/Y zero-padded' => ['23/02/2026', '2026-02-23'],
			'd/m/Y no padding' => ['3/2/2026', '2026-02-03'],
			'd/m/Y single digit day' => ['1/12/2026', '2026-12-01'],

			// d.m.Y - de-DE, de-AT, de-CH
			'd.m.Y zero-padded' => ['23.02.2026', '2026-02-23'],
			'd.m.Y no padding' => ['3.2.2026', '2026-02-03'],
			'd.m.Y single digit month' => ['15.1.2026', '2026-01-15'],

			// Y/m/d - ja-JP, zh-CN, ko-KR
			'Y/m/d zero-padded' => ['2026/02/23', '2026-02-23'],
			'Y/m/d no padding' => ['2026/2/3', '2026-02-03'],

			// m/d/Y - en-US (unambiguous cases where day > 12)
			'm/d/Y day > 12' => ['2/23/2026', '2026-02-23'],
			'm/d/Y both > 0' => ['1/31/2026', '2026-01-31'],

			// null/empty
			'null string' => ['null', null],
			'empty string' => ['', null],
			'whitespace' => ['  ', null],

			// Invalid
			'garbage' => ['not-a-date', null],
			'partial date' => ['02/2026', null],
		];
	}

	public function testParseDateFormatsInFullCsv(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		// ISO date without time
		$csv .= "\"Card ISO\"\t\"\"\t\"To Do\"\t\"\"\t\"2026-03-15\"\t\"2026-01-10\"\t\"2026-03-15\"\r\n";
		// German dots format
		$csv .= "\"Card DE\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"15.03.2026\"\t\"20.3.2026\"\r\n";
		// Japanese slashes format
		$csv .= "\"Card JP\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"2026/3/15\"\t\"2026/03/20\"\r\n";
		// US format (unambiguous, day > 12)
		$csv .= "\"Card US\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"3/15/2026\"\t\"3/20/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(4, $rows);

		// ISO date
		$this->assertEquals('2026-03-15', $rows[0]['duedate']->format('Y-m-d'));
		$this->assertEquals('2026-01-10', $rows[0]['createdAt']->format('Y-m-d'));

		// German dots
		$this->assertEquals('2026-03-15', $rows[1]['createdAt']->format('Y-m-d'));
		$this->assertEquals('2026-03-20', $rows[1]['lastModified']->format('Y-m-d'));

		// Japanese slashes
		$this->assertEquals('2026-03-15', $rows[2]['createdAt']->format('Y-m-d'));
		$this->assertEquals('2026-03-20', $rows[2]['lastModified']->format('Y-m-d'));

		// US format
		$this->assertEquals('2026-03-15', $rows[3]['createdAt']->format('Y-m-d'));
		$this->assertEquals('2026-03-20', $rows[3]['lastModified']->format('Y-m-d'));
	}

	public function testParseTagsSplitting(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"Card\"\t\"\"\t\"To Do\"\t\"Dev work, Chore, AI Supported,\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(1, $rows);
		$this->assertEquals(['Dev work', 'Chore', 'AI Supported'], $rows[0]['tags']);
	}

	public function testParseAssignedUsers(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Assigned users\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"Card\"\t\"\"\t\"To Do\"\t\"\"\t\"Anna Larch, John Doe,\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(1, $rows);
		$this->assertEquals(['Anna Larch', 'John Doe'], $rows[0]['assignedUsers']);
	}

	public function testParseEmptyCsv(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(0, $rows);
	}

	public function testParseEmptyContent(): void {
		$rows = $this->parser->parse('');

		$this->assertCount(0, $rows);
	}

	public function testParseEmptyTags(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"Card\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(1, $rows);
		$this->assertEquals([], $rows[0]['tags']);
	}

	public function testParseMultipleRows(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"Card 1\"\t\"Desc 1\"\t\"To Do\"\t\"Bug,\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";
		$csv .= "\"Card 2\"\t\"Desc 2\"\t\"Done\"\t\"Feature,\"\t\"null\"\t\"02/01/2026\"\t\"02/01/2026\"\r\n";
		$csv .= "\"Card 3\"\t\"Desc 3\"\t\"To Do\"\t\"\"\t\"null\"\t\"03/01/2026\"\t\"03/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(3, $rows);
		$this->assertEquals('Card 1', $rows[0]['title']);
		$this->assertEquals('Card 2', $rows[1]['title']);
		$this->assertEquals('Card 3', $rows[2]['title']);
		$this->assertEquals('To Do', $rows[0]['stackName']);
		$this->assertEquals('Done', $rows[1]['stackName']);
	}

	public function testParseWithIdColumn(): void {
		$csv = "\"ID\"\t\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"42\"\t\"Existing card\"\t\"Updated desc\"\t\"To Do\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";
		$csv .= "\"\"\t\"New card\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(2, $rows);
		$this->assertSame(42, $rows[0]['id']);
		$this->assertEquals('Existing card', $rows[0]['title']);
		$this->assertNull($rows[1]['id']);
		$this->assertEquals('New card', $rows[1]['title']);
	}

	public function testParseWithoutIdColumn(): void {
		$csv = "\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"My Card\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(1, $rows);
		$this->assertNull($rows[0]['id']);
	}

	public function testParseUtf8Bom(): void {
		$csv = "\xEF\xBB\xBF\"Card title\"\t\"Description\"\t\"List name\"\t\"Tags\"\t\"Due date\"\t\"Created\"\t\"Modified\"\r\n";
		$csv .= "\"Test\"\t\"\"\t\"To Do\"\t\"\"\t\"null\"\t\"01/01/2026\"\t\"01/01/2026\"\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(1, $rows);
		$this->assertEquals('Test', $rows[0]['title']);
	}

	public function testParseCommaDelimited(): void {
		$csv = "ID,Card title,Description,List name,Tags,Due date,Created,Modified\r\n";
		$csv .= "42,My Card,,To Do,\"Feature, Bug,\",null,01/01/2026,01/01/2026\r\n";
		$csv .= ",New Card,Some desc,Done,,null,02/01/2026,02/01/2026\r\n";

		$rows = $this->parser->parse($csv);

		$this->assertCount(2, $rows);
		$this->assertSame(42, $rows[0]['id']);
		$this->assertEquals('My Card', $rows[0]['title']);
		$this->assertEquals('To Do', $rows[0]['stackName']);
		$this->assertEquals(['Feature', 'Bug'], $rows[0]['tags']);
		$this->assertNull($rows[1]['id']);
		$this->assertEquals('New Card', $rows[1]['title']);
		$this->assertEquals('Done', $rows[1]['stackName']);
	}

	public function testParseCommaDelimitedUtf16Le(): void {
		$csv = "Card title,Description,List name,Tags,Due date,Created,Modified\r\n";
		$csv .= "Test,,To Do,,null,01/01/2026,01/01/2026\r\n";

		$utf16 = mb_convert_encoding($csv, 'UTF-16LE', 'UTF-8');
		$utf16WithBom = "\xFF\xFE" . $utf16;

		$rows = $this->parser->parse($utf16WithBom);

		$this->assertCount(1, $rows);
		$this->assertEquals('Test', $rows[0]['title']);
		$this->assertEquals('To Do', $rows[0]['stackName']);
	}
}
