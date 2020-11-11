<?php

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version1000Date20200308073933 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('deck_assigned_users');

		// Defaults to TYPE_USER = 0
		$table->addColumn('type', 'integer', [
			'notnull' => true,
			'default' => 0
		]);
		//$table->addIndex(['participant'], 'deck_assigned_users_idx_t');
		$table->addIndex(['type'], 'deck_assigned_users_idx_ty');

		return $schema;
	}
}
