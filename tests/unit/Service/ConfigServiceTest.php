<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConfigServiceTest extends TestCase {
	private IConfig&MockObject $config;
	private IGroupManager&MockObject $groupManager;
	private IEventDispatcher&MockObject $eventDispatcher;
	private ConfigService $service;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->service = new ConfigService(
			$this->config,
			$this->groupManager,
			$this->eventDispatcher,
		);
		self::invokePrivate($this->service, 'userId', ['admin']);
		$this->groupManager->method('isAdmin')->willReturn(true);
	}

	public function testSetGroupLimitLogsCriticalAction(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'deck.config.group_limit_updated'
				&& $event->getParameters() === ['groups' => 'admin, staff']));

		$this->service->set('groupLimit', [['id' => 'admin'], ['id' => 'staff']]);
	}

	public function testSetFederationEnabledLogsCriticalAction(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'deck.config.federation_updated'
				&& $event->getParameters() === ['federation' => 'enabled']));

		$this->service->set('federationEnabled', true);
	}

	public function testPersonalSettingIsNotLogged(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->set('calendar', true);
	}

	public function testGetDefaultBoardView(): void {
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('admin', 'deck', 'defaultBoardView', '')
			->willReturn('7');

		self::assertSame(7, $this->service->getDefaultBoardView());
	}

	public function testGetDefaultBoardViewNotSet(): void {
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('admin', 'deck', 'defaultBoardView', '')
			->willReturn('');

		self::assertNull($this->service->getDefaultBoardView());
	}

	public function testSetDefaultBoardView(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('admin', 'deck', 'defaultBoardView', '7');

		$this->service->set('defaultBoardView', 7);
	}

	public function testSetDefaultBoardViewToNull(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('admin', 'deck', 'defaultBoardView', '');

		$this->service->set('defaultBoardView', null);
	}
}
