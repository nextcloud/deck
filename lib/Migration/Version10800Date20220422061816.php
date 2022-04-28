<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Your name <your@email.com>
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
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version10800Date20220422061816 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$indexAdded = $this->addIndex($schema,
			'deck_boards',
			'idx_owner_modified',
			[ 'owner', 'last_modified' ]
		);

		$indexAdded = $this->addIndex($schema,
			'deck_board_acl',
			'idx_participant_type',
			[ 'participant', 'type']
		) || $indexAdded;

		$indexAdded = $this->addIndex($schema,
			'deck_cards',
			'idx_due_notified_archived_deleted', [
				'duedate', 'notified', 'archived', 'deleted_at'
			],
		) || $indexAdded;

		$indexAdded = $this->addIndex($schema,
			'deck_cards',
			'idx_last_editor', [
				'last_editor', 'description_prev'
			], [],
			// Adding a partial index on the description_prev as it is only used for a NULL check
			['lengths' => [null, 1]]
		) || $indexAdded;

		$indexAdded = $this->addIndex($schema,
			'deck_attachment',
			'idx_cardid_deletedat',
			[ 'card_id', 'deleted_at']
		) || $indexAdded;

		$indexAdded = $this->addIndex($schema,
			'deck_assigned_users',
			'idx_card_participant',
			[ 'card_id', 'participant']
		) || $indexAdded;

		return $indexAdded ? $schema : null;
	}

	private function addIndex(ISchemaWrapper $schema, string $table, string $indexName, array $columns, array $flags = [], array $options = []): bool {
		$table = $schema->getTable($table);
		if (!$table->hasIndex($indexName)) {
			$table->addIndex($columns, $indexName, $flags, $options);
			return true;
		}

		return false;
	}
}
