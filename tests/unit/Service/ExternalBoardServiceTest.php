<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Tests\Unit\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Federation\DeckFederationProxy;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\BoardMapper;
use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\ExternalBoardService;
use OCA\Deck\Service\PermissionService;
use OCP\Federation\ICloudIdManager;
use OCP\Federation\ICloudId;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class ExternalBoardServiceTest extends TestCase {
	public function testUpdateCardOnRemoteWithoutDoneKey(): void {
		$userManager = $this->createMock(IUserManager::class);
		$cloudIdManager = $this->createMock(ICloudIdManager::class);
		$proxy = $this->createMock(DeckFederationProxy::class);
		$configService = $this->createMock(ConfigService::class);
		$boardService = $this->createMock(BoardService::class);
		$permissionService = $this->createMock(PermissionService::class);
		$boardMapper = $this->createMock(\OCA\Deck\Db\BoardMapper::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);

		$board = $this->createMock(Board::class);
		$board->method('getShareToken')->willReturn('token');
		$board->method('getOwner')->willReturn('owner@remote');
		$board->method('getExternalId')->willReturn('ext-id');

		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getId')->willReturn('participant@local');
		$cloudId->method('getRemote')->willReturn('https://remote');

		$cloudIdManager->method('getCloudId')->willReturn($cloudId);
		$cloudIdManager->method('resolveCloudId')->willReturn($cloudId);

		$proxy->expects($this->once())
			->method('put')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback(function ($params) {
					return !array_key_exists('done', $params);
				})
			)
			->willReturn('response');

		$proxy->expects($this->once())
			->method('getOcsData')
			->willReturn(['id' => 1]);

		$service = new ExternalBoardService(
			$userManager,
			$cloudIdManager,
			$proxy,
			$configService,
			$boardService,
			$permissionService,
			$boardMapper,
			$urlGenerator,
			'user'
		);

		$service->updateCardOnRemote(
			$board,
			1,
			'Title',
			1,
			'plain',
			'owner',
			'Desc',
			0,
			null,
			null,
			null,
			null
		);
	}
}
