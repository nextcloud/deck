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
use OCA\Deck\Notification\NotificationHelper;
use OCP\IUser;
use \Test\TestCase;

class BoardServiceTest extends TestCase {

	/** @var BoardService */
	private $service;
	/** @var L10N */
	private $l10n;
	/** @var LabelMapper */
	private $labelMapper;
	/** @var AclMapper */
	private $aclMapper;
	/** @var BoardMapper */
	private $boardMapper;
	/** @var PermissionService */
	private $permissionService;
	/** @var NotificationHelper */
	private $notificationHelper;

	private $userId = 'admin';

	public function setUp() {
		parent::setUp();
		$this->l10n = $this->createMock(L10N::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->notificationHelper = $this->createMock(NotificationHelper::class);

		$this->service = new BoardService(
			$this->boardMapper,
			$this->l10n,
			$this->labelMapper,
			$this->aclMapper,
			$this->permissionService,
			$this->notificationHelper
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
	}

	public function testFindAll() {
		$b1 = new Board();
		$b1->setId(1);
		$b2 = new Board();
		$b2->setId(2);
		$b3 = new Board();
		$b3->setId(3);
		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->with('admin')
			->willReturn([$b1, $b2]);
		$this->boardMapper->expects($this->once())
			->method('findAllByGroups')
			->with('admin', ['a', 'b', 'c'])
			->willReturn([$b2, $b3]);
		$userinfo = [
			'user' => 'admin',
			'groups' => ['a', 'b', 'c']
		];
		$result = $this->service->findAll($userinfo);
		sort($result);
		$this->assertEquals([$b1, $b2, $b3], $result);
	}

	public function testFind() {
		$b1 = new Board();
		$b1->setId(1);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($b1);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$this->assertEquals($b1, $this->service->find(1));
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
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$b = $this->service->update(123, 'MyNewNameBoard', 'ffffff', false);

		$this->assertEquals($b->getTitle(), 'MyNewNameBoard');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getColor(), 'ffffff');
		$this->assertEquals($b->getArchived(), false);
	}

	public function testDelete() {
		$board = new Board();
		$board->setOwner('admin');
		$this->boardMapper->expects($this->once())
			->method('find')
			->willReturn($board);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$this->assertEquals($board, $this->service->delete(123));
	}

	public function testAddAcl() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType('user');
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		$acl->resolveRelation('participant', function($participant) use (&$user) {
			return null;
		});
		$this->notificationHelper->expects($this->once())
			->method('sendBoardShared');
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