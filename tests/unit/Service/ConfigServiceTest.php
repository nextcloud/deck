<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\BadRequestException;
use OCP\IConfig;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConfigServiceTest extends TestCase {

	private IConfig&MockObject $config;
	private IGroupManager&MockObject $groupManager;
	/** @var ConfigService|MockObject */
	private $configService;

	public function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		// ConfigService::getUserId() resolves the current user through
		// \OCP\Server::get(IUserSession::class) instead of a constructor
		// dependency, so it can't be mocked via the constructor. Stub just
		// that method and keep everything else on the real implementation.
		$this->configService = $this->getMockBuilder(ConfigService::class)
			->setConstructorArgs([$this->config, $this->groupManager])
			->onlyMethods(['getUserId'])
			->getMock();
		$this->configService->method('getUserId')->willReturn('admin');
	}

	public function testBoardOrderRoundTrip() {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('admin', 'deck', 'boardOrder', '[4,2,9]');
		$result = $this->configService->set('boardOrder', [4, '2', 9.0]);
		$this->assertSame([4, 2, 9], $result);
	}

	public function testBoardOrderRejectsNonArray() {
		$this->expectException(BadRequestException::class);
		$this->configService->set('boardOrder', 'not-a-list');
	}

	public function testGetBoardOrderDecodesAndFiltersStoredValue() {
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('admin', 'deck', 'boardOrder', '[]')
			->willReturn('[3,1,5,"x"]');
		$this->assertSame([3, 1, 5], $this->configService->get('boardOrder'));
	}

	public function testGetAllIncludesBoardOrder() {
		$this->config->method('getUserValue')->willReturnCallback(function ($userId, $appId, $key, $default) {
			if ($key === 'boardOrder') {
				return '[7,3]';
			}
			return $default;
		});
		$this->config->method('getAppValue')->willReturnArgument(2);
		$this->groupManager->method('isAdmin')->willReturn(false);

		$data = $this->configService->getAll();
		$this->assertSame([7, 3], $data['boardOrder']);
	}
}
