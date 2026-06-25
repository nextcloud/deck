<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Tests\Unit\ShareReview;

use OCA\Deck\Db\AclMapper;
use OCA\Deck\Service\BoardService;
use OCP\Share\Events\ShareReviewAccessCheckEvent;
use OCA\Deck\ShareReview\ShareReviewSource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareReviewSourceTest extends TestCase {
	private MockObject $aclMapper;
	private MockObject $logger;
	private MockObject $boardService;
	private MockObject $eventDispatcher;
	private MockObject $l;
	private ShareReviewSource $source;

	protected function setUp(): void {
		parent::setUp();
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')->willReturnCallback(
			function (string $text, array $params = []): string {
				return empty($params) ? $text : vsprintf($text, $params);
			}
		);
		$this->source = new ShareReviewSource(
			$this->aclMapper,
			$this->logger,
			$this->boardService,
			$this->eventDispatcher,
			$this->l,
		);
	}

	/** @param array<string, mixed> $overrides */
	private function makeShareRow(array $overrides = []): array {
		return array_merge([
			'id' => 1,
			'board_id' => 10,
			'type' => 0,
			'participant' => 'bob',
			'board_title' => 'My Board',
			'board_owner' => 'alice',
			'permission_edit' => 0,
			'permission_share' => 0,
			'permission_manage' => 0,
			'created_at' => 1700000000,
			'last_modified_at' => 0,
		], $overrides);
	}

	public function testGetName(): void {
		$this->assertSame('Deck', $this->source->getName());
	}

	public function testGetSharesEmpty(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn([]);

		$this->assertSame([], $this->source->getShares());
	}

	public function testGetSharesUserShare(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn([$this->makeShareRow()]);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$share = $shares[0];
		$this->assertSame(1, $share['id']);
		$this->assertArrayNotHasKey('app', $share);
		$this->assertSame('My Board (Board)', $share['object']);
		$this->assertSame('alice', $share['initiator']);
		$this->assertSame(IShare::TYPE_USER, $share['type']);
		$this->assertSame('bob', $share['recipient']);
		$this->assertSame(Constants::PERMISSION_READ, $share['permissions']);
		$this->assertFalse($share['password']);
		$this->assertSame(date('Y-m-d H:i:s', 1700000000), $share['time']);
		$this->assertSame('', $share['action']);
	}

	public function testGetSharesUsesLastModifiedAtWhenNewer(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['created_at' => 1700000000, 'last_modified_at' => 1800000000])]
		);

		$shares = $this->source->getShares();

		$this->assertSame(date('Y-m-d H:i:s', 1800000000), $shares[0]['time']);
	}

	public function testGetSharesGroupShare(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['type' => 1, 'participant' => 'developers'])]
		);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame(IShare::TYPE_GROUP, $shares[0]['type']);
		$this->assertSame('developers', $shares[0]['recipient']);
	}

	public function testGetSharesCircleShare(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['type' => 7, 'participant' => 'circle-uid'])]
		);

		$this->assertSame(IShare::TYPE_CIRCLE, $this->source->getShares()[0]['type']);
	}

	public function testGetSharesRemoteShare(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['type' => 6, 'participant' => 'user@remote.example'])]
		);

		$this->assertSame(IShare::TYPE_REMOTE, $this->source->getShares()[0]['type']);
	}

	public function testGetSharesUnknownTypeLogsWarningAndFallsBackToUser(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['type' => 99])]
		);
		$this->logger->expects($this->once())->method('warning');

		$this->assertSame(IShare::TYPE_USER, $this->source->getShares()[0]['type']);
	}

	public function testGetSharesMissingBoardFallback(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['board_id' => 42, 'board_title' => null, 'board_owner' => null])]
		);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame('Board 42 (Board)', $shares[0]['object']);
	}

	public function testGetSharesReturnsEmptyOnDbException(): void {
		$this->aclMapper->method('findAllForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertSame([], $this->source->getShares());
	}

	public function testComputePermissionsAllFalse(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permission_edit' => 0, 'permission_share' => 0, 'permission_manage' => 0])]
		);

		$this->assertSame(Constants::PERMISSION_READ, $this->source->getShares()[0]['permissions']);
	}

	public function testComputePermissionsEditFlag(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permission_edit' => 1])]
		);

		$expected = Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE | Constants::PERMISSION_DELETE;
		$this->assertSame($expected, $this->source->getShares()[0]['permissions']);
	}

	public function testComputePermissionsShareFlag(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permission_share' => 1])]
		);

		$expected = Constants::PERMISSION_READ | Constants::PERMISSION_SHARE;
		$this->assertSame($expected, $this->source->getShares()[0]['permissions']);
	}

	public function testComputePermissionsManageFlag(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permission_manage' => 1])]
		);

		$this->assertSame(Constants::PERMISSION_READ | 32, $this->source->getShares()[0]['permissions']);
	}

	public function testComputePermissionsAllTrue(): void {
		$this->aclMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permission_edit' => 1, 'permission_share' => 1, 'permission_manage' => 1])]
		);

		$expected = Constants::PERMISSION_READ
			| Constants::PERMISSION_UPDATE
			| Constants::PERMISSION_CREATE
			| Constants::PERMISSION_DELETE
			| Constants::PERMISSION_SHARE
			| 32;
		$this->assertSame($expected, $this->source->getShares()[0]['permissions']);
	}

	public function testDeleteShareNonNumericReturnsFalse(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->assertFalse($this->source->deleteShare('abc'));
	}

	public function testDeleteShareEventNotHandledReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ShareReviewAccessCheckEvent::class));
		$this->boardService->expects($this->never())->method('deleteAclForShareReview');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareEventDeniedReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ShareReviewAccessCheckEvent::class))
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->denyAccess('not in group');
			});
		$this->boardService->expects($this->never())->method('deleteAclForShareReview');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareEventGrantedReturnsTrue(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ShareReviewAccessCheckEvent::class))
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->boardService->expects($this->once())->method('deleteAclForShareReview')->with(7);

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareDoesNotExistReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->boardService->expects($this->once())
			->method('deleteAclForShareReview')
			->willThrowException($this->createMock(DoesNotExistException::class));

		$this->assertFalse($this->source->deleteShare('7'));
	}
}
