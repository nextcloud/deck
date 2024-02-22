<?php
/**
 * @copyright Copyright (c) 2022, chandi Langecker (git@chandi.it)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11000Date20240222115515 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$assigned_users_table = $schema->getTable('deck_assigned_users');
		if($assigned_users_table->hasIndex('deck_assigned_users_idx_c')) {
			$assigned_users_table->dropIndex('deck_assigned_users_idx_c');
		}

		$board_acl_table = $schema->getTable('deck_board_acl');
		if($board_acl_table->hasIndex('deck_board_acl_idx_i')) {
			$board_acl_table->dropIndex('deck_board_acl_idx_i');
		}
		return $schema;
	}
}
