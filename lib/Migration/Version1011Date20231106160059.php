<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1011Date20231106160059 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$createIndex = false;

		$table = $schema->getTable('deck_cards');

		if (!$table->hasIndex('idx_last_editor')) {
			$createIndex = true;
		}

		if (!$createIndex) {
			$index = $table->getIndex('idx_last_editor');
			if (in_array('description_prev', $index->getColumns(), true)) {
				$table->dropIndex('idx_last_editor');
				$createIndex = true;
			}
		}

		if ($createIndex) {
			$table->addIndex(['last_editor'], 'idx_last_editor');
			return $schema;
		}

		return null;
	}
}
