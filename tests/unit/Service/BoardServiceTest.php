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

namespace OCA\Deck\Service;

use OC\L10N\L10N;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCP\IGroupManager;
use OCP\ILogger;

class BoardServiceTest extends \PHPUnit_Framework_TestCase {

	private $service;
	private $logger;
	private $l10n;
	private $labelMapper;
	private $aclMapper;
	private $boardMapper;
	private $groupManager;
	private $permissionService;

	private $userId = 'admin';

	public function setUp() {
		$this->l10n = $this->getMockBuilder(L10N::class)
			->disableOriginalConstructor()
			->getMock();
		$this->aclMapper = $this->getMockBuilder(AclMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->boardMapper = $this->getMockBuilder(BoardMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->labelMapper = $this->getMockBuilder(LabelMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->permissionService = $this->getMockBuilder(PermissionService::class)
			->disableOriginalConstructor()->getMock();

		$this->service = new BoardService(
			$this->boardMapper,
			$this->l10n,
			$this->labelMapper,
			$this->aclMapper,
			$this->permissionService
		);
	}

	public function testFindAll() {
		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->with('admin')
			->willReturn([1,2,3,6,7]);
		$this->boardMapper->expects($this->once())
			->method('findAllByGroups')
			->with('admin', ['a', 'b', 'c'])
			->willReturn([4,5,6,7,8]);
		$userinfo = [
			'user' => 'admin',
			'groups' => ['a', 'b', 'c']
		];
		$result = $this->service->findAll($userinfo);
		sort($result);
		$this->assertEquals([1,2,3,4,5,6,7,8], $result);
	}

	public function testFind() {
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn(1);
		$this->assertEquals(1, $this->service->find(123));
	}

	public function testCreate() {
		$board = new Board();
        $board->setTitle('MyBoard');
        $board->setOwner('admin');
        $board->setColor('00ff00');
		$this->boardMapper->expects($this->once())
			->method('insert')
			->willReturn($board);
		$b = $this->service->create('MyBoard', 'admin', '00ff00');

		$this->assertEquals($b->getTitle(), 'MyBoard');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getColor(), '00ff00');
		$this->assertCount(4, $b->getLabels());
	}

	public function testUpdate() {
		$board = new Board();
		$board->setTitle('MyBoard');
		$board->setOwner('admin');
		$board->setColor('00ff00');
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($board);
		$this->boardMapper->expects($this->once())
			->method('update')
			->with($board)
			->willReturn($board);
		$b = $this->service->update(123, 'MyNewNameBoard', 'ffffff');

		$this->assertEquals($b->getTitle(), 'MyNewNameBoard');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getColor(), 'ffffff');
	}

	public function testDelete() {
		$this->boardMapper->expects($this->once())
			->method('find')
			->willReturn(new Board());
		$this->boardMapper->expects($this->once())
			->method('delete')
			->willReturn(1);
		$this->assertEquals(1, $this->service->delete(123));
	}

	public function testAddAcl() {
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType('user');
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		$this->aclMapper->expects($this->once())
			->method('insert')
			->with($acl)
			->willReturn($acl);
		$this->assertEquals($acl, $this->service->addAcl(
			123, 'user', 'admin', true, true, true
		));
	}

	public function testUpdateAcl() {
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType('user');
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);

		$this->aclMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($acl);
		$this->aclMapper->expects($this->once())
			->method('update')
			->with($acl)
			->willReturn($acl);

		$result = $this->service->updateAcl(
			123, false, false, false
		);

		$this->assertFalse($result->getPermissionEdit());
		$this->assertFalse($result->getPermissionShare());
		$this->assertFalse($result->getPermissionManage());

	}

	public function testDeleteAcl() {
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType('user');
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		$this->aclMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($acl);
		$this->aclMapper->expects($this->once())
			->method('delete')
			->with($acl)
			->willReturn(true);
		$this->assertTrue($this->service->deleteAcl(123));
	}
}