<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version10900Date202206151724222 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_sessions')) {
			$table = $schema->createTable('deck_sessions');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('board_id', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('last_contact', Types::INTEGER, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['board_id'], 'deck_session_board_id_idx');
			$table->addIndex(['token'], 'deck_session_token_idx');
			$table->addIndex(['last_contact'], 'deck_session_last_contact_idx');
		}
		return $schema;
	}
}
