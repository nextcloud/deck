<?php

declare(strict_types=1);
namespace OCA\Deck\Migration;

use Closure;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11001Date20251020122010 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		if ($schema->hasTable('deck_boards')) {
			$table = $schema->getTable('deck_boards');
			$table->addColumn('share_token', 'string', [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('external_id', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
		}
		return $schema;
	}
}
