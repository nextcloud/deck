<?php

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version10800Date20220427124317 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('deck_cards');
		if (!$table->hasColumn('valuecard')) {
			$table->addColumn('valuecard', 'integer', [
				'notnull' => false,
				'default' => null,
				'unsigned' => true,
			]);
		}

		return $schema;
	}
}
