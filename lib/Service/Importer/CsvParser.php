<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service\Importer;

use OCP\L10N\IFactory;

class CsvParser {

	private const HEADER_MAP = [
		'ID' => 'id',
		'Card title' => 'title',
		'Description' => 'description',
		'List name' => 'stackName',
		'Tags' => 'tags',
		'Assigned users' => 'assignedUsers',
		'Due date' => 'duedate',
		'Created' => 'createdAt',
		'Modified' => 'lastModified',
	];

	/** Date-only formats to try, ordered from unambiguous to locale-specific */
	private const DATE_FORMATS = [
		'Y-m-d',   // ISO date (sv-SE, lt-LT)
		'Y/n/j',   // ja-JP, zh-CN, ko-KR
		'j.n.Y',   // de-DE, de-AT, de-CH
		'j/n/Y',   // en-GB, most of Europe
	];

	/**
	 * Maps ICU date pattern characters to PHP date format characters.
	 * Only covers the short date patterns produced by IntlDateFormatter::SHORT.
	 */
	private const ICU_TO_PHP = [
		'dd' => 'd', 'd' => 'j',
		'MM' => 'm', 'M' => 'n',
		'yyyy' => 'Y', 'yy' => 'y',
		'y' => 'Y',
	];

	private ?string $localeFormat = null;

	public function __construct(
		?IFactory $l10nFactory = null,
	) {
		if ($l10nFactory !== null) {
			$this->localeFormat = $this->detectLocaleFormat($l10nFactory->findLocale());
		}
	}

	/**
	 * Parse CSV content (possibly UTF-16LE with BOM) into an array of associative arrays.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function parse(string $rawContent): array {
		$content = $this->convertEncoding($rawContent);
		$content = $this->stripUtf8Bom($content);

		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $content);
		rewind($stream);

		$delimiter = $this->detectDelimiter($stream);

		$headers = fgetcsv($stream, 0, $delimiter, '"', '"');
		if ($headers === false || $headers === [null]) {
			fclose($stream);
			return [];
		}

		$headerKeys = $this->mapHeaders($headers);

		$rows = [];
		while (($fields = fgetcsv($stream, 0, $delimiter, '"', '"')) !== false) {
			if ($fields === [null]) {
				continue;
			}

			$row = [];
			foreach ($headerKeys as $index => $key) {
				$value = $fields[$index] ?? '';
				$row[$key] = $value;
			}

			$rawId = trim($row['id'] ?? '');
			$row['id'] = ($rawId !== '' && is_numeric($rawId)) ? (int)$rawId : null;
			$row['tags'] = $this->splitCommaSeparated($row['tags'] ?? '');
			$row['assignedUsers'] = $this->splitCommaSeparated($row['assignedUsers'] ?? '');
			$row['duedate'] = $this->parseDate($row['duedate'] ?? '');
			$row['createdAt'] = $this->parseDate($row['createdAt'] ?? '');
			$row['lastModified'] = $this->parseDate($row['lastModified'] ?? '');

			$rows[] = $row;
		}

		fclose($stream);
		return $rows;
	}

	/**
	 * Detect delimiter by reading the first line and counting tab vs comma occurrences.
	 * Rewinds the stream after detection.
	 */
	private function detectDelimiter($stream): string {
		$firstLine = fgets($stream);
		rewind($stream);

		if ($firstLine === false) {
			return "\t";
		}

		$tabs = substr_count($firstLine, "\t");
		$commas = substr_count($firstLine, ',');

		return $tabs >= $commas ? "\t" : ',';
	}

	private function convertEncoding(string $content): string {
		// Detect UTF-16LE BOM (FF FE)
		if (strlen($content) >= 2 && $content[0] === "\xFF" && $content[1] === "\xFE") {
			$result = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
			return $result !== false ? $result : $content;
		}

		// Detect UTF-16BE BOM (FE FF)
		if (strlen($content) >= 2 && $content[0] === "\xFE" && $content[1] === "\xFF") {
			$result = mb_convert_encoding($content, 'UTF-8', 'UTF-16BE');
			return $result !== false ? $result : $content;
		}

		return $content;
	}

	private function stripUtf8Bom(string $content): string {
		if (str_starts_with($content, "\xEF\xBB\xBF")) {
			return substr($content, 3);
		}
		return $content;
	}

	/**
	 * @return array<int, string> index => normalized key
	 */
	private function mapHeaders(array $headers): array {
		$mapped = [];
		foreach ($headers as $index => $header) {
			$header = trim($header);
			$mapped[$index] = self::HEADER_MAP[$header] ?? $header;
		}
		return $mapped;
	}

	/**
	 * @return string[]
	 */
	private function splitCommaSeparated(string $value): array {
		if (trim($value) === '') {
			return [];
		}

		$items = explode(',', $value);
		$result = [];
		foreach ($items as $item) {
			$trimmed = trim($item);
			if ($trimmed !== '') {
				$result[] = $trimmed;
			}
		}
		return $result;
	}

	public function parseDate(?string $value): ?\DateTime {
		if ($value === null || trim($value) === '' || trim($value) === 'null') {
			return null;
		}

		$value = trim($value);

		// Try ISO 8601 with time first (e.g. 2026-02-20T19:00:00+00:00)
		$date = \DateTime::createFromFormat(\DateTime::ATOM, $value);
		if ($date !== false) {
			return $date;
		}

		// Try the user's locale format first if available
		if ($this->localeFormat !== null) {
			$date = \DateTime::createFromFormat($this->localeFormat, $value);
			if ($date !== false && $this->isCleanParse()) {
				$date->setTime(0, 0, 0);
				return $date;
			}
		}

		// Fall back to common formats
		foreach (self::DATE_FORMATS as $format) {
			$date = \DateTime::createFromFormat($format, $value);
			if ($date !== false && $this->isCleanParse()) {
				$date->setTime(0, 0, 0);
				return $date;
			}
		}

		// m/d/Y (en-US) — only when day > 12 to avoid ambiguity with d/m/Y
		$parts = explode('/', $value);
		if (count($parts) === 3 && (int)$parts[1] > 12) {
			$date = \DateTime::createFromFormat('n/j/Y', $value);
			if ($date !== false && $this->isCleanParse()) {
				$date->setTime(0, 0, 0);
				return $date;
			}
		}

		return null;
	}

	/**
	 * Use IntlDateFormatter to derive the PHP date format for the user's locale.
	 */
	private function detectLocaleFormat(string $locale): ?string {
		if (!class_exists(\IntlDateFormatter::class)) {
			return null;
		}

		$formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);
		/** @var \IntlDateFormatter $formatter */

		$icuPattern = $formatter->getPattern();
		return $this->icuToPhpFormat($icuPattern);
	}

	/**
	 * Convert an ICU short date pattern (e.g. "dd/MM/yyyy" or "M/d/yy")
	 * to a PHP DateTime::createFromFormat string.
	 */
	private function icuToPhpFormat(string $icuPattern): ?string {
		// Replace longer tokens first to avoid partial matches
		$phpFormat = $icuPattern;
		// Sort keys by length descending so 'dd' is replaced before 'd', etc.
		$replacements = self::ICU_TO_PHP;
		uksort($replacements, fn ($a, $b) => strlen($b) - strlen($a));

		foreach ($replacements as $icu => $php) {
			$phpFormat = str_replace($icu, $php, $phpFormat);
		}

		// Verify no ICU tokens remain (would indicate an unsupported pattern)
		if (preg_match('/[a-zA-Z]/', preg_replace('/[djnmYy]/', '', $phpFormat))) {
			return null;
		}

		return $phpFormat;
	}

	/**
	 * Check that the last createFromFormat call produced no warnings or errors
	 * (e.g. month 13 silently overflowing to January of next year).
	 */
	private function isCleanParse(): bool {
		$errors = \DateTime::getLastErrors();
		return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
	}
}
