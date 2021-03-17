<?php

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010404Date20210305 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('deck_boards');
        if (!$table->hasColumn('upcoming_show_only_assigned_cards')) {
            $table->addColumn('upcoming_show_only_assigned_cards', 'boolean', [
                'notnull' => false,
                'default' => true
            ]);
        }
        return $schema;
	}
}
