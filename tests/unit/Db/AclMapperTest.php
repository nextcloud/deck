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

namespace OCA\Deck\Db;

use Test\AppFramework\Db\MapperTestUtility;

/**
 * Class AclMapperTest
 * @package OCA\Deck\Db
 * @group DB
 */
class AclMapperTest extends MapperTestUtility  {

	private $mapper;
	private $acl;

	public function setup(){
		parent::setUp();

		$this->dbConnection = \OC::$server->getDatabaseConnection();
		$this->mapper = new AclMapper($this->db);
        $this->mapperDatabase = new AclMapper($this->dbConnection);

        //$acl = $this->getAcl('user','user1');
		//$this->mapperDatabase->insert($acl);
	}
	/** @return Acl */
	public function getAcl($type='user', $participant='admin', $write=false, $invite=false, $manage=false, $boardId=123) {
		$acl = new Acl();
		$acl->setParticipant($participant);
		$acl->setType('user');
		$acl->setPermissionWrite($write);
		$acl->setPermissionInvite($invite);
		$acl->setPermissionManage($manage);
		$acl->setBoardId($boardId);
		return $acl;
	}

	public function testFindAll() {
		$acls = [];
		$acls[] = $this->getAcl('user','user1')->jsonSerialize();
		$acls[] = $this->getAcl('user','user2')->jsonSerialize();
		$acls[] = $this->getAcl('group','group1')->jsonSerialize();
		$acls[] = $this->getAcl('group','group2', true, true, true, 234)->jsonSerialize();

		$sql = 'SELECT id, board_id, type, participant, permission_write, permission_invite, permission_manage FROM `*PREFIX*deck_board_acl` WHERE `board_id` = ? ';
		$params = [123];
		$rows = [
			$acls[0], $acls[1], $acls[2]
		];
		$this->setMapperResult($sql, $params, $rows);

		$result = $this->mapper->findAll(123);
		//$this->assertEquals($rows, $result);

	}

}