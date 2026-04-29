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

class Version11002Date20260429000000 extends SimpleMigrationStep {
	/**
	 * Add owner_type to deck_boards to support non-user board owners (e.g. circles/teams).
	 *
	 * Values mirror the Acl::PERMISSION_TYPE_* constants:
	 *   0 = user (default, preserves existing behaviour)
	 *   7 = circle/team
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('deck_boards')) {
			$table = $schema->getTable('deck_boards');
			if (!$table->hasColumn('owner_type')) {
				$table->addColumn('owner_type', 'smallint', [
					'notnull' => true,
					'default' => 0,
				]);
			}
		}

		return $schema;
	}
}
