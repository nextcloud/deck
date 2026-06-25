<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version10200Date20201111150114 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Fix wrong index added in Version1000Date20200308073933
		$table = $schema->getTable('deck_assigned_users');
		if ($table->hasIndex('deck_assigned_users_idx_t')) {
			$table->dropIndex('deck_assigned_users_idx_t');
			if (!$table->hasIndex('deck_assigned_users_idx_ty')) {
				$table->addIndex(['type'], 'deck_assigned_users_idx_ty');
			}
		}

		// Check consistency of the labels table when updating from a version < 0.6
		// git commit for database.xml change at b0eaae6705dbfb9ce834d4047912d3e34eaa157f
		$table = $schema->getTable('deck_labels');
		if (!$table->hasColumn('last_modified')) {
			$table->addColumn('last_modified', 'integer', [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		// Check consistency of the cards table when updating from a version < 0.5.1
		// git commit for database.xml change at dd104466d61e32f59552da183034522e04effe35
		$table = $schema->getTable('deck_cards');
		if (!$table->hasColumn('description_prev')) {
			$table->addColumn('description_prev', 'text', [
				'notnull' => false,
			]);
		}
		if (!$table->hasColumn('last_editor')) {
			$table->addColumn('last_editor', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
		}

		// Check consistency of the cards table when updating from a version < 0.5.0
		// git commit for database.xml change at a068d6e1c6588662f0ea131e57f974238538eda6
		$table = $schema->getTable('deck_boards');
		if (!$table->hasColumn('last_modified')) {
			$table->addColumn('last_modified', 'integer', [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
		}
		$table = $schema->getTable('deck_stacks');
		if (!$table->hasColumn('last_modified')) {
			$table->addColumn('last_modified', 'integer', [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		// Check consistency of the cards table when updating from a version < 0.5.0
		// git commit for database.xml change at ef4ce31c47a5ef70d1a4d00f2d4cd182ac067f2c
		$table = $schema->getTable('deck_stacks');
		if (!$table->hasColumn('deleted_at')) {
			$table->addColumn('deleted_at', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		// Check consistency of the cards table when updating from a version < 0.5.0
		// git commit for database.xml change at 2ef4b55af427d90412544e77916e9449db7dbbcd
		$table = $schema->getTable('deck_cards');
		if (!$table->hasColumn('deleted_at')) {
			$table->addColumn('deleted_at', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		$table = $schema->getTable('deck_cards');
		if ($table->getColumn('title')->getLength() !== 255) {
			$table->changeColumn('title', [
				'length' => 255
			]);
		}

		return $schema;
	}
}
