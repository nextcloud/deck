<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCP\IConfig;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;

class ConfigServiceTest extends \Test\TestCase {
	private IConfig|MockObject $config;
	private IGroupManager|MockObject $groupManager;
	private ConfigService $service;

	public function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->service = new ConfigService(
			$this->config,
			$this->groupManager,
		);

		// The service lazily reads userId from IUserSession via OCP\Server::get().
		// We bypass that by setting the private property directly so set() can run.
		$ref = new \ReflectionProperty(ConfigService::class, 'userId');
		$ref->setValue($this->service, 'admin');
	}

	public function testSetSwimlaneModeIsStoredShared(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'board:5:swimlaneMode', 'labels');
		$this->config->expects($this->never())
			->method('setUserValue');

		$result = $this->service->set('board:5:swimlaneMode', 'labels');
		$this->assertSame('labels', $result);
	}

	public function testSetSwimlaneLabelOrderIsShared(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'board:7:swimlaneLabelOrder', '[3,1,2]');
		$this->config->expects($this->never())
			->method('setUserValue');

		$result = $this->service->set('board:7:swimlaneLabelOrder', '[3,1,2]');
		$this->assertSame('[3,1,2]', $result);
	}

	public function testSetSwimlaneUserOrderIsShared(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'board:7:swimlaneUserOrder', '["admin","alice"]');
		$this->config->expects($this->never())
			->method('setUserValue');

		$result = $this->service->set('board:7:swimlaneUserOrder', '["admin","alice"]');
		$this->assertSame('["admin","alice"]', $result);
	}

	public function testSetBoardConfigRejectsEmptyKey(): void {
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->expectException(BadRequestException::class);
		$this->service->set('board:5:', 'x');
	}

	public function testSetBoardConfigRejectsEmptyBoardId(): void {
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->expectException(BadRequestException::class);
		$this->service->set('board::swimlaneMode', 'labels');
	}

	public function testSetSwimlaneConfigRejectsNonNumericBoardId(): void {
		// ConfigController::setValue() only permission-checks keys matching
		// board:(\d+): — a non-numeric id would bypass it, so the shared
		// setAppValue path must refuse it.
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->expectException(BadRequestException::class);
		$this->service->set('board:abc:swimlaneMode', 'labels');
	}

	public function testSetSwimlaneModeRejectsUnknownMode(): void {
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->expectException(BadRequestException::class);
		$this->service->set('board:5:swimlaneMode', 'banana');
	}

	public function testSetSwimlaneOrderRejectsNonJsonValue(): void {
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->expectException(BadRequestException::class);
		$this->service->set('board:5:swimlaneLabelOrder', 'not json');
	}

	public function testSetSwimlaneOrderRejectsJsonObject(): void {
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->expectException(BadRequestException::class);
		$this->service->set('board:5:swimlaneLabelOrder', '{"a":1}');
	}

	public function testSetSwimlaneOrderRejectsInvalidEntries(): void {
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->expectException(BadRequestException::class);
		$this->service->set('board:5:swimlaneUserOrder', '[true]');
	}

	public function testSetSwimlaneOrderAcceptsCatchAllLaneId(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'board:5:swimlaneLabelOrder', '[3,1,"__none__"]');
		$this->config->expects($this->never())->method('setUserValue');

		$result = $this->service->set('board:5:swimlaneLabelOrder', '[3,1,"__none__"]');
		$this->assertSame('[3,1,"__none__"]', $result);
	}

	public function testNonSwimlaneBoardSettingStaysPerUser(): void {
		// board:5:calendar is a per-user setting (setUserValue path)
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('admin', Application::APP_ID, 'board:5:calendar', 'yes');

		$this->service->set('board:5:calendar', 'yes');
	}

	public function testSetBoardConfigWithoutSettingPartIsIgnored(): void {
		// upstream behavior: 'board:5' (fewer than 3 segments) is silently ignored
		$this->config->expects($this->never())->method('setAppValue');
		$this->config->expects($this->never())->method('setUserValue');

		$this->assertNull($this->service->set('board:5', 'x'));
	}
}
