<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

/**
 * The canonical form of an imported card, which the importing app and
 * Deck hash independently to verify a card (SHA-256, lowercase hex)
 *
 * Eleven values, each JSON-encoded and joined with "\n": ref, stack ref,
 * title, description, order, start, due, done, assignees, label refs and
 * file IDs. Times are RFC 3339 in UTC with seconds ("2026-01-02T03:04:05Z")
 * or null; lists are arrays, never null, with strings sorted by byte and
 * file IDs as ascending numbers. Strings are encoded like Go's
 * `json.Marshal`: UTF-8 unescaped, `/` unescaped, `<`, `>` and `&` as
 * `\u003c`, `\u003e` and `\u0026`, U+2028 and U+2029 as `\u2028` and
 * `\u2029`, other control characters as `\b`, `\f`, `\n`, `\r`, `\t` or
 * `\u00XX` (lowercase hex).
 *
 * @since 2.0.0
 */
final class CardCanonicalForm {
	/**
	 * @param list<string> $assignees
	 * @param list<string> $labelRefs
	 * @param list<int> $fileIds
	 */
	public static function hash(string $ref, string $stackRef, string $title, string $description, int $order,
		?\DateTimeInterface $start, ?\DateTimeInterface $due, ?\DateTimeInterface $done,
		array $assignees, array $labelRefs, array $fileIds): string {
		sort($assignees, SORT_STRING);
		sort($labelRefs, SORT_STRING);
		sort($fileIds, SORT_NUMERIC);
		$values = [
			$ref,
			$stackRef,
			$title,
			$description,
			$order,
			self::time($start),
			self::time($due),
			self::time($done),
			$assignees,
			$labelRefs,
			$fileIds,
		];
		return hash('sha256', implode("\n", array_map(self::encode(...), $values)));
	}

	public static function time(?\DateTimeInterface $time): ?string {
		if ($time === null) {
			return null;
		}
		return \DateTimeImmutable::createFromInterface($time)
			->setTimezone(new \DateTimeZone('UTC'))
			->format('Y-m-d\TH:i:s\Z');
	}

	private static function encode(mixed $value): string {
		$json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		// These characters can only occur inside strings
		return strtr($json, ['<' => '\u003c', '>' => '\u003e', '&' => '\u0026']);
	}
}
