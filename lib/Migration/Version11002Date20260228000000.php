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

class Version11002Date20260228000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		if ($schema->hasTable('deck_stacks')) {
			$table = $schema->getTable('deck_stacks');
			if (!$table->hasColumn('is_done_column')) {
				$table->addColumn('is_done_column', 'boolean', [
					'notnull' => false,
					'default' => false,
				]);
			}
		}
		return $schema;
	}
}
