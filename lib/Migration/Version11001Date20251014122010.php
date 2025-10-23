<?php

declare(strict_types=1);
namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
class Version11001Date20251014122010 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		$tableName = 'deck_boards_external';
		if (!$schema->hasTable($tableName)) {
			$table = $schema->createTable($tableName);
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('external_id', 'integer', [
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
			$table->addColumn('participant', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
		}
		return $schema;
	}
}
