<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer;

class ImportOptionsTest extends \Test\TestCase {
	public function testEverythingIsImportedByDefault(): void {
		$options = new ImportOptions();

		self::assertTrue($options->importCards);
		self::assertTrue($options->importArchivedCards);
		self::assertTrue($options->importDoneState);
		self::assertTrue($options->importDueDates);
		self::assertTrue($options->importLabels);
		self::assertTrue($options->importAssignments);
		self::assertTrue($options->importComments);
		self::assertTrue($options->importAttachments);
		self::assertTrue($options->importSharing);
	}

	public function testEmptyRequestInputImportsEverything(): void {
		self::assertEquals(new ImportOptions(), ImportOptions::fromArray([]));
	}

	/**
	 * A typo in one of the keys would silently import that part anyway, so every
	 * flag is checked individually.
	 *
	 * @dataProvider dataFlagNames
	 */
	public function testEveryFlagCanBeTurnedOffOnItsOwn(string $key): void {
		$options = ImportOptions::fromArray([$key => '0']);

		self::assertFalse($options->$key, $key . ' was not read from the request');
		foreach (array_column(self::dataFlagNames(), 0) as $other) {
			if ($other !== $key) {
				self::assertTrue($options->$other, $other . ' must not be affected by ' . $key);
			}
		}
	}

	public static function dataFlagNames(): array {
		return [
			['importCards'],
			['importArchivedCards'],
			['importDoneState'],
			['importDueDates'],
			['importLabels'],
			['importAssignments'],
			['importComments'],
			['importAttachments'],
			['importSharing'],
		];
	}

	public function testMissingKeysKeepImportingEverything(): void {
		$options = ImportOptions::fromArray(['importComments' => '0']);

		self::assertFalse($options->importComments);
		self::assertTrue($options->importCards);
		self::assertTrue($options->importAttachments);
	}

	/**
	 * @dataProvider dataFlagValues
	 */
	public function testFlagsAreParsedFromRequestInput(mixed $value, bool $expected): void {
		self::assertSame($expected, ImportOptions::fromArray(['importCards' => $value])->importCards);
	}

	public static function dataFlagValues(): array {
		return [
			'string zero' => ['0', false],
			'string one' => ['1', true],
			'string false' => ['false', false],
			'string true' => ['true', true],
			'boolean false' => [false, false],
			'boolean true' => [true, true],
			'empty string' => ['', false],
			'unparsable value falls back to importing' => ['maybe', true],
			// a key that is present but null counts as "off", only a missing key defaults to importing
			'explicit null' => [null, false],
			'integer zero' => [0, false],
			'integer one' => [1, true],
			'form checkbox on' => ['on', true],
			'form checkbox off' => ['off', false],
			'string no' => ['no', false],
			'string yes' => ['yes', true],
		];
	}
}
