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

/**
 * Adds deck_import_refs: the objects an importing app wrote through
 * OCA\Deck\Import\IBoardImporter, keyed by the app's own references
 */
class Version11002Date20261030120000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('deck_import_refs')) {
			return null;
		}

		$table = $schema->createTable('deck_import_refs');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('app', Types::STRING, [
			'notnull' => true,
			'length' => 32,
		]);
		$table->addColumn('kind', Types::STRING, [
			'notnull' => true,
			'length' => 8,
		]);
		// The board an object belongs to; 0 for boards themselves
		$table->addColumn('board_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
			'default' => 0,
		]);
		$table->addColumn('ref', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('ref_hash', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('object_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('source_hash', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		$table->addColumn('stored_hash', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		$table->addColumn('state', Types::SMALLINT, [
			'notnull' => true,
			'default' => 0,
		]);
		$table->addColumn('created_at', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
			'default' => 0,
		]);
		$table->addColumn('updated_at', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
			'default' => 0,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['app', 'kind', 'board_id', 'ref_hash'], 'deck_import_refs_uq');
		$table->addIndex(['kind', 'object_id'], 'deck_import_refs_obj');

		return $schema;
	}
}
