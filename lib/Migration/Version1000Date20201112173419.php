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
class Version1000Date20201112173419 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();
		$table = $schema->getTable('deck_cards');

		$table->addColumn('time_to_complete', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('time_to_complete_minutes', 'integer', [
			'notnull' => false,
		]);
		$table->addColumn('milestone', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('product', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('component', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('pct_complete', 'integer', [
			'notnull' => false,
		]);

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
