<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);
namespace OCA\Deck\Migration;

use Closure;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11002Date20260410000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_dependent_cards')) {
			$table = $schema->createTable('deck_dependent_cards');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('card_id', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('dependent_card_id', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['card_id', 'dependent_card_id'], 'deck_depend_cards_uidx');
			$table->addIndex(['card_id'], 'deck_depend_cards_idx_c');
			$table->addIndex(['dependent_card_id'], 'deck_depend_cards_idx_d');
		}
		return $schema;
	}
}
