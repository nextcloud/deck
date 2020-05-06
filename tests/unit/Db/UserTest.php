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

class UserTest extends \Test\TestCase {
	public function testGroupObjectSerialize() {
		/** @var IUser $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('myuser');
		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn('myuser displayname');
		$userRelationalObject = new User($user);
		$expected = [
			'uid' => 'myuser',
			'displayname' => 'myuser displayname',
			'type' => 0
		];
		$this->assertEquals($expected, $userRelationalObject->getObjectSerialization());
	}

	public function testGroupJSONSerialize() {
		/** @var IUser $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('myuser');
		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn('myuser displayname');
		$userRelationalObject = new User($user);
		$expected = [
			'uid' => 'myuser',
			'displayname' => 'myuser displayname',
			'primaryKey' => 'myuser',
			'type' => 0,
		];
		$this->assertEquals($expected, $userRelationalObject->jsonSerialize());
	}
}
