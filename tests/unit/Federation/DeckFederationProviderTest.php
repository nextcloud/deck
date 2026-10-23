<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Federation;

use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Service\ConfigService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class DeckFederationProviderTest extends TestCase {
	private BoardMapper&MockObject $boardMapper;
	private IEventDispatcher&MockObject $eventDispatcher;
	private DeckFederationProvider $provider;

	public function setUp(): void {
		parent::setUp();

		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $method) {
			$notification->method($method)->willReturnSelf();
		}
		$notificationManager = $this->createMock(INotificationManager::class);
		$notificationManager->method('createNotification')->willReturn($notification);

		$this->provider = new DeckFederationProvider(
			$this->createMock(ICloudIdManager::class),
			$notificationManager,
			$this->boardMapper,
			$this->createMock(AclMapper::class),
			$this->createMock(ChangeHelper::class),
			$this->createMock(ConfigService::class),
			$this->eventDispatcher,
		);
	}

	public function testShareReceivedLogsCriticalAction(): void {
		$share = $this->createMock(ICloudFederationShare::class);
		$share->method('getResourceName')->willReturn('Remote board');
		$share->method('getProviderId')->willReturn('77');
		$share->method('getSharedBy')->willReturn('alice@remote.example');
		$share->method('getShareWith')->willReturn('bob');
		$this->boardMapper->method('insert')
			->willReturnCallback(static function (Board $board): Board {
				$board->setId(5);
				return $board;
			});

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'deck.board_share.received'
				&& $event->getLogMessage() === 'Board %s was shared by "%s" with "%s" from the remote board %s'
				&& $event->getParameters() === ['boardId' => 5, 'sharedBy' => 'alice@remote.example', 'sharedWith' => 'bob', 'remoteBoardId' => '77']));

		$this->assertSame('5', $this->provider->shareReceived($share));
	}
}
