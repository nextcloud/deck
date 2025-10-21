<?php

declare(strict_types=1);
namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
class Version11001Date20251016122010 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		$tableName = 'deck_boards_external';
		if ($schema->hasTable($tableName)) {
			$table = $schema->getTable('deck_boards_external');
			$table->addColumn('share_token', 'string', [
				'notnull' => true,
				'length' => 32,
			]);
		}
		return $schema;
	}
}
