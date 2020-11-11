<?php

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version10200Date20201111150114 extends SimpleMigrationStep {


	function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

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

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
