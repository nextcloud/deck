<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11002Date20260611000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('deck_board_acl');

		if (!$table->hasColumn('created_at')) {
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		if (!$table->hasColumn('last_modified_at')) {
			$table->addColumn('last_modified_at', 'integer', [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		return $schema;
	}
}
