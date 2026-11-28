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
use OCP\IUserManager;

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
		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->any())
			->method('get')
			->willReturn($user);
		$userRelationalObject = new User('myuser', $userManager);
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
		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->any())
			->method('get')
			->willReturn($user);
		$userRelationalObject = new User('myuser', $userManager);
		$expected = [
			'uid' => 'myuser',
			'displayname' => 'myuser displayname',
			'primaryKey' => 'myuser',
			'type' => 0,
		];
		$this->assertEquals($expected, $userRelationalObject->jsonSerialize());
	}

	public function testIgnoresAccountPropertyScope() {
		// Regression: IUserManager::getDisplayName() returns the UID when the
		// requesting user is not allowed to see the target user's display name
		// (account-property visibility scope). Serialization must use the IUser
		// directly so collaborators on the same board always see real names.
		/** @var IUser $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('WBE32');
		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn('Pedro Jota');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->any())
			->method('get')
			->willReturn($user);
		$userManager->expects($this->any())
			->method('getDisplayName')
			->willReturn('WBE32');
		$userRelationalObject = new User('WBE32', $userManager);
		$serialized = $userRelationalObject->getObjectSerialization();
		$this->assertSame('Pedro Jota', $serialized['displayname']);
	}
}
