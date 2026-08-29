<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Tests\Unit\ShareReview;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\ShareReview\ShareReviewSource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\IPaginatedShareReviewSource;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewPermission;
use OCP\Share\ShareReview\ShareReviewQuery;
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

	public function testGetDisplayNameIsTheBrandName(): void {
		$this->assertInstanceOf(IPaginatedShareReviewSource::class, $this->source);
		$this->assertSame('Deck', $this->source->getDisplayName());
	}

	public function testGetSharesFallsBackToCreatedAtWhenLastModifiedIsUnset(): void {
		$this->mockFindAllForShareReview([$this->makeShareRow(['created_at' => 1700000000, 'last_modified_at' => 0])]);

		$this->assertSame(1700000000, $this->source->getShares()[0]->lastModifiedTimestamp);
	}

	/** @param list<array<string, mixed>> $rows */
	private function mockFindAllForShareReview(array $rows): void {
		$this->aclMapper->method('findAllForShareReview')->willReturnCallback(static function () use ($rows): \Generator {
			yield from $rows;
		});
	}

	public function testQuerySharesReturnsPageWithMapperCounts(): void {
		$query = new ShareReviewQuery(limit: 2, offset: 4, search: 'board');
		$counts = new ShareReviewCounts(10, 3);
		$this->aclMapper->expects($this->once())
			->method('findPageForShareReview')
			->with($query, null, null)
			->willReturn([$this->makeShareRow(['id' => 5]), $this->makeShareRow(['id' => 6, 'type' => 1, 'participant' => 'developers'])]);
		$this->aclMapper->expects($this->once())
			->method('countForShareReview')
			->with($query, null, null)
			->willReturn($counts);

		$page = $this->source->queryShares($query);

		$this->assertSame($counts, $page->counts);
		$this->assertSame(['5', '6'], array_map(static fn (ShareReviewEntry $e) => $e->id, $page->entries));
		$this->assertSame(IShare::TYPE_GROUP, $page->entries[1]->type);
	}

	public function testQuerySharesTranslatesTypesAndPermissionIdsToNativeFilters(): void {
		$query = new ShareReviewQuery(shareTypes: [IShare::TYPE_GROUP, IShare::TYPE_LINK, IShare::TYPE_CIRCLE], permissionIds: [ShareReviewSource::PERMISSION_MANAGE, 'files:read', ShareReviewSource::PERMISSION_EDIT]);
		$this->aclMapper->expects($this->once())
			->method('findPageForShareReview')
			->with($query, [Acl::PERMISSION_TYPE_GROUP, Acl::PERMISSION_TYPE_CIRCLE], ['permission_edit', 'permission_manage'])
			->willReturn([]);
		$this->aclMapper->method('countForShareReview')->willReturn(new ShareReviewCounts(0, 0));

		$this->source->queryShares($query);
	}

	public function testForeignTypesAndPermissionIdsMatchNothing(): void {
		$query = new ShareReviewQuery(shareTypes: [IShare::TYPE_LINK], permissionIds: ['files:read']);
		$this->aclMapper->expects($this->once())
			->method('countForShareReview')
			->with($query, [], [])
			->willReturn(new ShareReviewCounts(4, 0));

		$this->assertSame(0, $this->source->countShares($query)->filteredCount);
	}

	public function testReadPermissionIsGrantedByEveryAclAndDisablesTheFilter(): void {
		$query = new ShareReviewQuery(permissionIds: [ShareReviewSource::PERMISSION_READ]);
		$this->aclMapper->expects($this->once())
			->method('countForShareReview')
			->with($query, null, null)
			->willReturn(new ShareReviewCounts(4, 4));

		$this->assertSame(4, $this->source->countShares($query)->filteredCount);
	}

	public function testGetSharesStreamsTheFullIdOrderedList(): void {
		$this->mockFindAllForShareReview(array_map(fn (int $id) => $this->makeShareRow(['id' => $id]), range(1, ShareReviewQuery::MAX_LIMIT + 1)));

		$this->assertCount(ShareReviewQuery::MAX_LIMIT + 1, $this->source->getShares());
	}

	public function testQuerySharesReturnsEmptyPageOnDbException(): void {
		$this->aclMapper->method('findPageForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$page = $this->source->queryShares(new ShareReviewQuery());

		$this->assertSame([], $page->entries);
		$this->assertSame(0, $page->counts->totalCount);
	}

	public function testCountSharesByTypeSkipsUnknownNativeTypes(): void {
		$this->aclMapper->method('countByTypeForShareReview')->willReturn([
			Acl::PERMISSION_TYPE_GROUP => 2,
			Acl::PERMISSION_TYPE_USER => 3,
			99 => 1,
		]);

		// unknown native types are excluded from the shareTypes filter, so
		// the counts must exclude them too
		$this->assertSame([IShare::TYPE_GROUP => 2, IShare::TYPE_USER => 3], $this->source->countSharesByType(new ShareReviewQuery()));
	}

	public function testGetShareIsAKeyedLookup(): void {
		$this->aclMapper->expects($this->never())->method('findPageForShareReview');
		$this->aclMapper->expects($this->once())
			->method('findForShareReview')
			->with(7)
			->willReturn($this->makeShareRow(['id' => 7, 'participant' => 'carol', 'permission_edit' => 1]));

		$entry = $this->source->getShare('7');

		$this->assertNotNull($entry);
		$this->assertSame('7', $entry->id);
		$this->assertSame('carol', $entry->recipient);
		$this->assertSame([ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_EDIT], $this->permissionIds($entry->permissions));
	}

	public function testGetShareUnknownOrInvalidIdReturnsNull(): void {
		$this->aclMapper->method('findForShareReview')->willReturn(null);

		$this->assertNull($this->source->getShare('7'));
		$this->assertNull($this->source->getShare('abc'));
		$this->assertNull($this->source->getShare('1e3'));
	}

	public function testGetShareReturnsNullOnDbException(): void {
		$this->aclMapper->method('findForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertNull($this->source->getShare('7'));
	}

	public function testGetSharesEmpty(): void {
		$this->mockFindAllForShareReview([]);

		$this->assertSame([], $this->source->getShares());
	}

	public function testGetSharesUserShare(): void {
		$this->mockFindAllForShareReview([$this->makeShareRow()]);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$share = $shares[0];
		$this->assertInstanceOf(ShareReviewEntry::class, $share);
		$this->assertSame('1', $share->id);
		$this->assertSame('My Board (Board)', $share->object);
		$this->assertSame('alice', $share->initiator);
		$this->assertSame(IShare::TYPE_USER, $share->type);
		$this->assertSame('bob', $share->recipient);
		$this->assertSame([ShareReviewSource::PERMISSION_READ], $this->permissionIds($share->permissions));
		$this->assertFalse($share->hasPassword);
		$this->assertSame(1700000000, $share->lastModifiedTimestamp);
		$this->assertSame('', $share->action);
	}

	public function testGetSharesUsesLastModifiedAtWhenNewer(): void {
		$this->mockFindAllForShareReview([$this->makeShareRow(['created_at' => 1700000000, 'last_modified_at' => 1800000000])]);

		$shares = $this->source->getShares();

		$this->assertSame(1800000000, $shares[0]->lastModifiedTimestamp);
	}

	public function testGetSharesGroupShare(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['type' => 1, 'participant' => 'developers'])]
		);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame(IShare::TYPE_GROUP, $shares[0]->type);
		$this->assertSame('developers', $shares[0]->recipient);
	}

	public function testGetSharesCircleShare(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['type' => 7, 'participant' => 'circle-uid'])]
		);

		$this->assertSame(IShare::TYPE_CIRCLE, $this->source->getShares()[0]->type);
	}

	public function testGetSharesRemoteShare(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['type' => 6, 'participant' => 'user@remote.example'])]
		);

		$this->assertSame(IShare::TYPE_REMOTE, $this->source->getShares()[0]->type);
	}

	public function testGetSharesUnknownTypeLogsWarningAndFallsBackToUser(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['type' => 99])]
		);
		$this->logger->expects($this->once())->method('warning');

		$this->assertSame(IShare::TYPE_USER, $this->source->getShares()[0]->type);
	}

	public function testGetSharesMissingBoardFallback(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['board_id' => 42, 'board_title' => null, 'board_owner' => null])]
		);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame('Board 42 (Board)', $shares[0]->object);
	}

	public function testGetSharesReturnsEmptyOnDbException(): void {
		$this->aclMapper->method('findAllForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertSame([], $this->source->getShares());
	}

	public function testPermissionsAllFlagsFalseEmitsReadOnly(): void {
		$this->mockFindAllForShareReview([$this->makeShareRow(['permission_edit' => 0, 'permission_share' => 0, 'permission_manage' => 0])]);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsEditFlagEmitsSingleEditPermission(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permission_edit' => 1])]
		);

		$permissions = $this->source->getShares()[0]->permissions;
		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_EDIT],
			$this->permissionIds($permissions)
		);
		$this->assertSame('Edit', $permissions[1]->displayName);
		$this->assertNotNull($permissions[1]->hint);
	}

	public function testPermissionsShareFlagEmitsSharePermission(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permission_share' => 1])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_SHARE],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsManageFlagEmitsManagePermission(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permission_manage' => 1])]
		);

		$permissions = $this->source->getShares()[0]->permissions;
		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_MANAGE],
			$this->permissionIds($permissions)
		);
		$this->assertSame('Manage board', $permissions[1]->displayName);
		$this->assertNotNull($permissions[1]->hint);
		$this->assertSame(30, $permissions[1]->priority);
	}

	public function testPermissionsManageFlagOffEmitsNoManagePermission(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permission_edit' => 1, 'permission_share' => 1, 'permission_manage' => 0])]
		);

		$this->assertNotContains(
			ShareReviewSource::PERMISSION_MANAGE,
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsAllFlagsTrueEmitsFullSet(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permission_edit' => 1, 'permission_share' => 1, 'permission_manage' => 1])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_EDIT, ShareReviewSource::PERMISSION_SHARE, ShareReviewSource::PERMISSION_MANAGE],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionIdentifiers(): void {
		$this->assertSame('deck:read', ShareReviewSource::PERMISSION_READ);
		$this->assertSame('deck:edit', ShareReviewSource::PERMISSION_EDIT);
		$this->assertSame('deck:share', ShareReviewSource::PERMISSION_SHARE);
		$this->assertSame('deck:manage', ShareReviewSource::PERMISSION_MANAGE);
	}

	/**
	 * @param list<ShareReviewPermission> $permissions
	 * @return list<string>
	 */
	private function permissionIds(array $permissions): array {
		return array_map(static fn (ShareReviewPermission $permission): string => $permission->id, $permissions);
	}

	public function testDeleteShareNonNumericReturnsFalse(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->assertFalse($this->source->deleteShare('abc'));
		$this->assertFalse($this->source->deleteShare('12.9'), 'only ids the source emits are accepted');
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
