<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * @group DB
 */
class BoardDatabaseTest extends \Test\TestCase {
	public const TEST_USER1 = "test-share-user1";
	public const TEST_USER2 = "test-share-user2";
	public const TEST_USER3 = "test-share-user3";
	public const TEST_USER4 = "test-share-user4";
	public const TEST_GROUP1 = "test-share-group1";

	/** @var \OCA\Deck\Service\BoardService */
	private $boardService;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($backend);
		$backend->createUser(self::TEST_USER1, self::TEST_USER1);
		$backend->createUser(self::TEST_USER2, self::TEST_USER2);
		$backend->createUser(self::TEST_USER3, self::TEST_USER3);
		$backend->createUser(self::TEST_USER4, self::TEST_USER4);
		// create group
		$groupBackend = new \Test\Util\Group\Dummy();
		$groupBackend->createGroup(self::TEST_GROUP1);
		$groupBackend->createGroup('group');
		$groupBackend->createGroup('group1');
		$groupBackend->createGroup('group2');
		$groupBackend->createGroup('group3');
		$groupBackend->addToGroup(self::TEST_USER1, 'group');
		$groupBackend->addToGroup(self::TEST_USER2, 'group');
		$groupBackend->addToGroup(self::TEST_USER3, 'group');
		$groupBackend->addToGroup(self::TEST_USER2, 'group1');
		$groupBackend->addToGroup(self::TEST_USER3, 'group2');
		$groupBackend->addToGroup(self::TEST_USER4, 'group3');
		$groupBackend->addToGroup(self::TEST_USER2, self::TEST_GROUP1);
		\OC::$server->getGroupManager()->addBackend($groupBackend);
	}
	public function setUp(): void {
		parent::setUp();
		\OC::$server->getUserSession()->login(self::TEST_USER1, self::TEST_USER1);
		$this->boardService = \OC::$server->query("\OCA\Deck\Service\BoardService");
	}
	public function testCreate() {
		$board = new \OCA\Deck\Db\Board();
		$board->setTitle('Test');
		$board->setOwner(self::TEST_USER1);
		$board->setColor('000000');
		$board->setLabels([]);
		$created = $this->boardService->create('Test', self::TEST_USER1,  '000000');
		$id = $created->getId();
		$actual = $this->boardService->find($id);
		$this->assertEquals($actual->getTitle(), $board->getTitle());
		$this->assertEquals($actual->getColor(), $board->getColor());
		$this->assertEquals($actual->getOwner(), $board->getOwner());
		$this->boardService->deleteForce($id);
	}

	public function tearDown(): void {
		parent::tearDown();
	}
}
