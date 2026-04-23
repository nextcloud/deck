<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Bootstrap for running unit tests that have no Nextcloud server dependencies.
 * Usage: vendor/bin/phpunit --bootstrap tests/bootstrap-standalone.php tests/unit/Service/Importer/CsvParserTest.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function (string $class) {
	$prefix = 'OCA\\Deck\\';
	if (str_starts_with($class, $prefix)) {
		$relativeClass = substr($class, strlen($prefix));
		$file = __DIR__ . '/../lib/' . str_replace('\\', '/', $relativeClass) . '.php';
		if (file_exists($file)) {
			require_once $file;
		}
	}
});
