<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Model\Probes\DataProbe;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CirclesServiceTest extends TestCase {
	private IAppManager&MockObject $appManager;

	protected function setUp(): void {
		parent::setUp();
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('isEnabledForUser')->with('circles')->willReturn(true);
	}

	public function testGetUserCirclesStopsSessionOnSuccess(): void {
		$circle = $this->createMock(Circle::class);
		$circle->expects($this->once())
			->method('getSingleId')
			->willReturn('circle-1');

		$federatedUser = $this->createMock(FederatedUser::class);
		$manager = $this->getMockBuilder(CirclesManager::class)
			->disableOriginalConstructor()
			->onlyMethods(['getFederatedUser', 'startSession', 'probeCircles', 'stopSession'])
			->getMock();

		$manager->expects($this->once())
			->method('getFederatedUser')
			->with('alice', Member::TYPE_USER)
			->willReturn($federatedUser);
		$manager->expects($this->once())
			->method('startSession')
			->with($federatedUser);
		$manager->expects($this->once())
			->method('probeCircles')
			->with($this->isInstanceOf(CircleProbe::class))
			->willReturn([$circle]);
		$manager->expects($this->once())
			->method('stopSession');

		$service = $this->getMockBuilder(CirclesService::class)
			->setConstructorArgs([$this->appManager])
			->onlyMethods(['getCirclesManager'])
			->getMock();
		$service->expects($this->once())
			->method('getCirclesManager')
			->willReturn($manager);

		$result = $service->getUserCircles('alice');
		$this->assertSame(['circle-1'], $result);
	}

	public function testGetUserCirclesStopsSessionOnFailure(): void {
		$federatedUser = $this->createMock(FederatedUser::class);
		$manager = $this->getMockBuilder(CirclesManager::class)
			->disableOriginalConstructor()
			->onlyMethods(['getFederatedUser', 'startSession', 'probeCircles', 'stopSession'])
			->getMock();

		$manager->expects($this->once())
			->method('getFederatedUser')
			->with('alice', Member::TYPE_USER)
			->willReturn($federatedUser);
		$manager->expects($this->once())
			->method('startSession')
			->with($federatedUser);
		$manager->expects($this->once())
			->method('probeCircles')
			->with($this->isInstanceOf(CircleProbe::class))
			->willThrowException(new \RuntimeException('Boom'));
		$manager->expects($this->once())
			->method('stopSession');

		$service = $this->getMockBuilder(CirclesService::class)
			->setConstructorArgs([$this->appManager])
			->onlyMethods(['getCirclesManager'])
			->getMock();
		$service->expects($this->once())
			->method('getCirclesManager')
			->willReturn($manager);

		$this->assertSame([], $service->getUserCircles('alice'));
	}

	public function testGetCircleStopsSuperSession(): void {
		$circle = $this->createMock(Circle::class);
		$manager = $this->getMockBuilder(CirclesManager::class)
			->disableOriginalConstructor()
			->onlyMethods(['startSuperSession', 'probeCircle', 'stopSession'])
			->getMock();

		$manager->expects($this->once())
			->method('startSuperSession');
		$manager->expects($this->once())
			->method('probeCircle')
			->with('circle-1', null, $this->isInstanceOf(DataProbe::class))
			->willReturn($circle);
		$manager->expects($this->once())
			->method('stopSession');

		$service = $this->getMockBuilder(CirclesService::class)
			->setConstructorArgs([$this->appManager])
			->onlyMethods(['getCirclesManager'])
			->getMock();
		$service->expects($this->once())
			->method('getCirclesManager')
			->willReturn($manager);

		$this->assertSame($circle, $service->getCircle('circle-1'));
	}
}
