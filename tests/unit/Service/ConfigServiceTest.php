<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\NoPermissionException;
use OCP\IConfig;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;

class ConfigServiceTest extends \Test\TestCase {
	private IConfig|MockObject $config;
	private IGroupManager|MockObject $groupManager;
	private PermissionService|MockObject $permissionService;
	private BoardMapper|MockObject $boardMapper;
	private ConfigService $service;

	public function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);

		$this->service = new ConfigService(
			$this->config,
			$this->groupManager,
			$this->permissionService,
			$this->boardMapper,
		);

		// The service lazily reads userId from IUserSession via OCP\Server::get().
		// We bypass that by setting the private property directly so set() can run.
		$ref = new \ReflectionProperty(ConfigService::class, 'userId');
		$ref->setAccessible(true);
		$ref->setValue($this->service, 'admin');
	}

	public function testSetSwimlaneModeChecksEditPermissionAndStoresShared(): void {
		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 5, Acl::PERMISSION_EDIT);
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'board:5:swimlaneMode', 'labels');
		$this->config->expects($this->never())
			->method('setUserValue');

		$result = $this->service->set('board:5:swimlaneMode', 'labels');
		$this->assertSame('labels', $result);
	}

	public function testSetSwimlaneLabelOrderIsShared(): void {
		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 7, Acl::PERMISSION_EDIT);
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'board:7:swimlaneLabelOrder', '[3,1,2]');

		$this->service->set('board:7:swimlaneLabelOrder', '[3,1,2]');
	}

	public function testSetSwimlaneUserOrderIsShared(): void {
		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 7, Acl::PERMISSION_EDIT);
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'board:7:swimlaneUserOrder', '["admin","alice"]');

		$this->service->set('board:7:swimlaneUserOrder', '["admin","alice"]');
	}

	public function testSetSwimlaneModeRequiresEditPermission(): void {
		$this->permissionService
			->method('checkPermission')
			->willThrowException(new NoPermissionException('No edit permission on board'));
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->expectException(NoPermissionException::class);
		$this->service->set('board:5:swimlaneMode', 'labels');
	}

	public function testNonSwimlaneBoardSettingStaysPerUser(): void {
		// board:5:calendar is a per-user setting (no permission check, setUserValue path)
		$this->permissionService->expects($this->never())->method('checkPermission');
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('admin', Application::APP_ID, 'board:5:calendar', 'yes');

		$this->service->set('board:5:calendar', 'yes');
	}
}
