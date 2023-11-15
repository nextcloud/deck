<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Your name <your@email.com>
 *
 * @author Your name <your@email.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
