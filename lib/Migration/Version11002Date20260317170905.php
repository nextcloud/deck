<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);
namespace OCA\Deck\Migration;

use Closure;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11002Date20260317170905 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		if ($schema->hasTable('deck_cards')) {
			$table = $schema->getTable('deck_cards');
			if (!$table->hasColumn('color')) {
				$table->addColumn('color', 'string', [
					'notnull' => false,
					'length' => 6,
				]);
			}
		}
		return $schema;
	}
}
