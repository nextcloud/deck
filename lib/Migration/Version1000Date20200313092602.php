<?php

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20200313092602 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('deck_board_settings')) {
			$table = $schema->createTable('deck_board_settings');
			$table->addColumn('board_id', 'bigint', [
				'notnull' => true,
			]);
			$table->addColumn('key', 'string', [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('user', 'string', [
				'length' => 64,
			]);
			$table->addColumn('value', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->setPrimaryKey(['board_id', 'key', 'user']);
		}

		return $schema;
	}

}
