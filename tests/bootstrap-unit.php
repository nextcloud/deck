<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Custom PHPUnit bootstrap for deck unit tests.
 * Loads the Nextcloud server bootstrap first, then defines stub classes for
 * optional app dependencies (e.g. circles) that may not be installed in the
 * test environment.
 */

require_once __DIR__ . '/../../../tests/bootstrap.php';
require_once __DIR__ . '/stubs.php';
