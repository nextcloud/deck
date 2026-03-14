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

class Version11001Date20260314120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$tableName = 'deck_cards';
		if ($schema->hasTable($tableName)) {
			$table = $schema->getTable($tableName);
			if (!$table->hasColumn('dav_uri')) {
				$table->addColumn('dav_uri', 'string', [
					'notnull' => false,
					'length' => 255,
				]);
				$table->addIndex(['dav_uri'], 'deck_cards_dav_uri_index');
			}
		}

		return $schema;
	}
}
