<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11100Date20260123000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_stack_automations')) {
			$table = $schema->createTable('deck_stack_automations');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('stack_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('event', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('action_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('action_config', Types::TEXT, [
				'notnull' => true,
				'default' => '{}',
			]);
			$table->addColumn('order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['stack_id'], 'deck_stack_auto_stack_idx');
			$table->addIndex(['event'], 'deck_stack_auto_event_idx');

			return $schema;
		}

		return null;
	}
}
