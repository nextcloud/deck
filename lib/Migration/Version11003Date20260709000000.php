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

class Version11003Date20260709000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		if ($schema->hasTable('deck_labels')) {
			$table = $schema->getTable('deck_labels');
			if (!$table->hasColumn('order')) {
				$table->addColumn('order', 'integer', [
					'notnull' => false,
					'default' => null,
				]);
			}
		}
		return $schema;
	}
}
