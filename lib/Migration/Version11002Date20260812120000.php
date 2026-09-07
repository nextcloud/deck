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

class Version11002Date20260812120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_boards')) {
			return null;
		}

		$table = $schema->getTable('deck_boards');
		if (!$table->hasColumn('team_id')) {
			$table->addColumn('team_id', 'string', [
				'notnull' => false,
				'length' => 64,
				'default' => null,
			]);
		}

		if (!$table->hasIndex('deck_boards_team_id')) {
			$table->addIndex(['team_id'], 'deck_boards_team_id');
		}

		return $schema;
	}
}
