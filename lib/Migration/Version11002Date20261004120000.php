<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Swimlane settings as columns on deck_boards, so they go with the board.
 */
class Version11002Date20261004120000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_boards')) {
			return null;
		}

		$table = $schema->getTable('deck_boards');

		if (!$table->hasColumn('swimlane_mode')) {
			$table->addColumn('swimlane_mode', 'string', [
				'notnull' => false,
				'length' => 16,
				'default' => null,
			]);
		}

		// text, an order may hold 32768 bytes and Oracle caps varchar at 4000.
		// No default, MySQL rejects one on TEXT. Null is read as '[]'.
		foreach (['swimlane_label_order', 'swimlane_user_order'] as $column) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, 'text', [
					'notnull' => false,
				]);
			}
		}

		return $schema;
	}
}
