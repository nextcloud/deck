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

class Version1000Date20200306161713 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_boards')) {
			$table = $schema->createTable('deck_boards');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('owner', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('color', 'string', [
				'notnull' => false,
				'length' => 6,
			]);
			$table->addColumn('archived', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->addColumn('deleted_at', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_modified', 'integer', [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('deck_stacks')) {
			$table = $schema->createTable('deck_stacks');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('board_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('order', 'bigint', [
				'notnull' => false,
				'length' => 8,
			]);
			$table->addColumn('deleted_at', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_modified', 'integer', [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['board_id'], 'deck_stacks_board_id_index');
			$table->addIndex(['order'], 'deck_stacks_order_index');
		}

		if (!$schema->hasTable('deck_cards')) {
			$table = $schema->createTable('deck_cards');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('description', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('description_prev', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('stack_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('type', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => 'plain',
			]);
			$table->addColumn('last_modified', 'integer', [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_editor', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('owner', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('order', 'bigint', [
				'notnull' => false,
				'length' => 8,
			]);
			$table->addColumn('archived', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->addColumn('duedate', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('notified', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->addColumn('deleted_at', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['stack_id'], 'deck_cards_stack_id_index');
			$table->addIndex(['order'], 'deck_cards_order_index');
			$table->addIndex(['archived'], 'deck_cards_archived_index');
		}

		if (!$schema->hasTable('deck_attachment')) {
			$table = $schema->createTable('deck_attachment');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('card_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('type', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('data', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('last_modified', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('created_at', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('created_by', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('deleted_at', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('deck_labels')) {
			$table = $schema->createTable('deck_labels');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => false,
				'length' => 100,
			]);
			$table->addColumn('color', 'string', [
				'notnull' => false,
				'length' => 6,
			]);
			$table->addColumn('board_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('last_modified', 'integer', [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['board_id'], 'deck_labels_board_id_index');
		}

		if (!$schema->hasTable('deck_assigned_labels')) {
			$table = $schema->createTable('deck_assigned_labels');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('label_id', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('card_id', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['label_id'], 'deck_assigned_labels_idx_i');
			$table->addIndex(['card_id'], 'deck_assigned_labels_idx_c');
		}

		if (!$schema->hasTable('deck_assigned_users')) {
			$table = $schema->createTable('deck_assigned_users');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('participant', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('card_id', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['participant'], 'deck_assigned_users_idx_p');
			//$table->addIndex(['card_id'], 'deck_assigned_users_idx_c');
		}

		if (!$schema->hasTable('deck_board_acl')) {
			$table = $schema->createTable('deck_board_acl');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('board_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('type', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('participant', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('permission_edit', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->addColumn('permission_share', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->addColumn('permission_manage', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['board_id', 'type', 'participant'], 'deck_board_acl_uq_i');
			//$table->addIndex(['board_id'], 'deck_board_acl_idx_i');
		}
		return $schema;
	}
}
