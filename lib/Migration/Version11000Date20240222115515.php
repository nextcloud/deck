<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11000Date20240222115515 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$returnValue = null;

		$assignedUsersTable = $schema->getTable('deck_assigned_users');
		if ($assignedUsersTable->hasIndex('deck_assigned_users_idx_c')) {
			$assignedUsersTable->dropIndex('deck_assigned_users_idx_c');
			$returnValue = $schema;
		}

		$boardAclTable = $schema->getTable('deck_board_acl');
		if ($boardAclTable->hasIndex('deck_board_acl_idx_i')) {
			$boardAclTable->dropIndex('deck_board_acl_idx_i');
			$returnValue = $schema;
		}
		return $returnValue;
	}
}
