<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Db;

use OCP\IUser;

class User extends RelationalObject {
	public function __construct(IUser $user) {
		$primaryKey = $user->getUID();
		parent::__construct($primaryKey, $user);
	}

	public function getObjectSerialization() {
		return [
			'uid' => $this->object->getUID(),
			'displayname' => $this->object->getDisplayName(),
			'type' => 0
		];
	}

	public function getUID() {
		return $this->object->getUID();
	}

	public function getDisplayName() {
		return $this->object->getDisplayName();
	}
}
