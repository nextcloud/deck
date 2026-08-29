<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Migration;

use Closure;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11003Date20260821120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_board_views')) {
			$table = $schema->createTable('deck_board_views');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('board_id', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('filters', 'text', [
				'notnull' => true,
			]);
			$table->addColumn('owner', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('last_modified_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['board_id', 'owner'], 'deck_board_views_idx_board_owner');
		}
		return $schema;
	}
}
