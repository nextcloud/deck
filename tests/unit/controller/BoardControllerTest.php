<?php

declare(strict_types=1);

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

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\Importer\BoardImportService;
use OCA\Deck\Service\PermissionService;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

class BoardControllerTest extends \Test\TestCase {
	private IL10N&MockObject $l10n;
	private BoardController $controller;
	private IRequest&MockObject $request;
	private IUserManager&MockObject $userManager;
	private IGroupManager&MockObject $groupManager;
	private BoardService&MockObject $boardService;
	private PermissionService&MockObject $permissionService;
	private BoardImportService&MockObject $boardImportService;
	private $userId = 'user';

	public function setUp(): void {
		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->groupManager = $this->getMockBuilder(IGroupManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->boardService = $this->getMockBuilder(BoardService::class)
			->disableOriginalConstructor()
			->getMock();
		$this->permissionService = $this->getMockBuilder(PermissionService::class)
			->disableOriginalConstructor()
			->getMock();
		$this->boardImportService = $this->getMockBuilder(BoardImportService::class)
			->disableOriginalConstructor()
			->getMock();

		$user = $this->createMock(IUser::class);
		$this->groupManager->method('getUserGroupIds')
			->willReturn(['admin', 'group1', 'group2']);
		$this->userManager->method('get')
			->with($this->userId)
			->willReturn($user);

		$this->controller = new BoardController(
			'deck',
			$this->request,
			$this->boardService,
			$this->permissionService,
			$this->boardImportService,
			$this->l10n,
			$this->userId
		);
	}


	public function testIndex() {
		$this->boardService->expects($this->once())
			->method('findAll')
			->willReturn([1, 2, 3]);

		$actual = $this->controller->index();
		$this->assertEquals([1, 2, 3], $actual);
	}

	public function testRead() {
		$board = new Board();
		$this->boardService->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($board);
		$this->assertEquals($board, $this->controller->read(123));
	}

	public function testCreate() {
		$board = $this->createMock(Board::class);
		$this->boardService->expects($this->once())
			->method('create')
			->with('abc', 'user', 'green')
			->willReturn($board);
		$this->assertEquals($board, $this->controller->create('abc', 'green'));
	}

	public function testUpdate(): void {
		$board = $this->createMock(Board::class);
		$this->boardService->expects($this->once())
			->method('update')
			->with(1, 'abc', 'green', false)
			->willReturn($board);
		$this->assertEquals($board, $this->controller->update(1, 'abc', 'green', false));
	}

	public function testDelete(): void {
		$board = $this->createMock(Board::class);
		$this->boardService->expects($this->once())
			->method('delete')
			->with(123)
			->willReturn($board);
		$this->assertEquals($board, $this->controller->delete(123));
	}

	public function testDeleteUndo() {
		$board = $this->createMock(Board::class);
		$this->boardService->expects($this->once())
			->method('deleteUndo')
			->with(123)
			->willReturn($board);
		$this->assertEquals($board, $this->controller->deleteUndo(123));
	}

	public function testGetUserPermissions() {
		$acl = [
			Acl::PERMISSION_READ => true,
			Acl::PERMISSION_EDIT => true,
			Acl::PERMISSION_MANAGE => true,
			Acl::PERMISSION_SHARE => true,
		];
		$expected = [
			'PERMISSION_READ' => true,
			'PERMISSION_EDIT' => true,
			'PERMISSION_MANAGE' => true,
			'PERMISSION_SHARE' => true,
		];
		$this->permissionService->expects($this->once())
			->method('getPermissions')
			->with(123)
			->willReturn($acl);
		$this->assertEquals($expected, $this->controller->getUserPermissions(123));
	}

	public function testAddAcl(): void {
		$acl = $this->createMock(Acl::class);
		$this->boardService->expects($this->once())
			->method('addAcl')
			->with(1, 2, 'user1', true, true, true)
			->willReturn($acl);
		$this->assertEquals($acl, $this->controller->addAcl(1, 2, 'user1', true, true, true));
	}

	public function testUpdateAcl(): void {
		$acl = $this->createMock(Acl::class);
		$this->boardService->expects($this->once())
			->method('updateAcl')
			->with(1, true, true, true)
			->willReturn($acl);
		$this->assertEquals($acl, $this->controller->updateAcl(1, true, true, true));
	}

	public function testDeleteAcl() {
		$acl = $this->getMockBuilder(Acl::class)
			->disableOriginalConstructor()
			->getMock();

		$this->boardService->expects($this->once())
			->method('deleteAcl')
			->with(1)
			->willReturn($acl);
		$this->assertEquals($acl, $this->controller->deleteAcl(1));
	}
}
