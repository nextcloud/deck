<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Tests\Unit\ShareReview;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\ShareReview\ShareReviewSource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\IPaginatedShareReviewSource;
use OCP\Share\ShareReview\IShareReviewSourceSnapshot;
use OCP\Share\ShareReview\ShareReviewActionContext;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPermission;
use OCP\Share\ShareReview\ShareReviewQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

// The OCP\Share\ShareReview API ships with the server, not with
// nextcloud/ocp, so a server that predates it needs the local stubs. They are
// required here rather than from tests/bootstrap.php: making that file the
// phpunit bootstrap would also register the app's composer autoloader, and the
// nextcloud/ocp dev-master package it pulls in then shadows the OCP classes of
// the server under test.
if (!interface_exists(IShareReviewSource::class)) {
	require_once __DIR__ . '/Stubs.php';
}

final class ShareReviewSourceTest extends TestCase {
	private MockObject $aclMapper;
	private MockObject $logger;
	private MockObject $boardService;
	private MockObject $eventDispatcher;
	private MockObject $l;
	private MockObject $userSession;
	private ShareReviewSource $source;
	/** @var list<object> every event the source dispatched */
	private array $dispatched = [];

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
		$this->userSession = $this->createMock(IUserSession::class);
		$this->source = new ShareReviewSource(
			$this->aclMapper,
			$this->logger,
			$this->boardService,
			$this->eventDispatcher,
			$this->l,
			$this->userSession,
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

	public function testSerializeAndRestoreRoundTripAnAcl(): void {
		$this->assertInstanceOf(IShareReviewSourceSnapshot::class, $this->source);
		$this->aclMapper->method('findForShareReview')->with(7)->willReturn([
			'id' => 7, 'board_id' => 2, 'type' => Acl::PERMISSION_TYPE_USER, 'participant' => 'bob',
			'permission_edit' => true, 'permission_share' => false, 'permission_manage' => false,
		]);

		$snapshot = $this->source->serializeShare('7');

		$this->assertIsString($snapshot);
		$data = json_decode($snapshot, true);
		$this->assertSame(1, $data['v']);
		$this->assertSame(2, $data['boardId']);
		$this->assertSame('bob', $data['participant']);
		$this->assertSame(['edit' => true, 'share' => false, 'manage' => false], $data['permissions']);

		// getId() comes from Entity's magic __call, so a real entity is needed
		$restored = new Acl();
		$restored->setId(9);
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->boardService->expects($this->once())->method('restoreAclForShareReview')
			->with(2, Acl::PERMISSION_TYPE_USER, 'bob', true, false, false)
			->willReturn($restored);

		$this->assertTrue($this->source->restoreShare($snapshot));
		[$message, $parameters] = $this->auditEntries()[0];
		$this->assertStringContainsString('restored', $message);
		$this->assertSame('9', $parameters[0]);
	}

	public function testSerializeOfAMissingOrNonCanonicalIdIsNull(): void {
		$this->aclMapper->method('findForShareReview')->willReturn(null);

		$this->assertNull($this->source->serializeShare('7'));
		$this->assertNull($this->source->serializeShare('12.9'));
	}

	public function testRestoreRejectsAForeignSnapshot(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->assertFalse($this->source->restoreShare('not json'));
		$this->assertFalse($this->source->restoreShare('{"v":99,"boardId":2,"type":0,"participant":"bob","permissions":[]}'));
		$this->assertFalse($this->source->restoreShare('{"v":1,"boardId":2}'), 'an incomplete snapshot is refused');
	}

	public function testRestoreDeniedByTheAccessCheckIsAudited(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->denyAccess('not an operator'));
		$this->boardService->expects($this->never())->method('restoreAclForShareReview');

		$this->assertFalse($this->source->restoreShare('{"v":1,"boardId":2,"type":0,"participant":"bob","permissions":{"edit":true}}'));
		$this->assertStringContainsString('denied', $this->auditEntries()[0][0]);
	}

	public function testRestoreOfAFederatedAclIsReportedAsFailure(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->boardService->method('restoreAclForShareReview')->willThrowException(new BadRequestException('federated'));
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($this->source->restoreShare('{"v":1,"boardId":2,"type":6,"participant":"bob@remote","permissions":{"edit":true}}'));
	}

	public function testRestoreForwardsTheActionContext(): void {
		$captured = null;
		$this->eventDispatcher->method('dispatchTyped')->willReturnCallback(function (object $event) use (&$captured): void {
			if ($event instanceof ShareReviewAccessCheckEvent) {
				$captured = $event;
				$event->denyAccess('no');
			}
		});

		$this->source->restoreShare('{"v":1,"boardId":2,"type":0,"participant":"bob","permissions":{"edit":true}}', new ShareReviewActionContext('alice', ShareReviewAccessCheckEvent::SCOPE_SELF));

		$this->assertSame(ShareReviewAccessCheckEvent::ACTION_RESTORE, $captured->getAction());
		$this->assertSame('alice', $captured->getActingUserId());
		$this->assertSame(ShareReviewAccessCheckEvent::SCOPE_SELF, $captured->getScope());
	}

	public function testCountSharesByInitiatorDelegatesWithTheLimit(): void {
		$this->aclMapper->expects($this->once())->method('countByInitiatorForShareReview')
			->with($this->isInstanceOf(ShareReviewQuery::class), 10, null, null)
			->willReturn(['alice' => 3, 'bob' => 1]);

		$this->assertSame(['alice' => 3, 'bob' => 1], $this->source->countSharesByInitiator(new ShareReviewQuery(), 10));
	}

	public function testCountSharesByInitiatorRejectsAnOutOfRangeLimit(): void {
		$this->aclMapper->expects($this->never())->method('countByInitiatorForShareReview');

		$this->expectException(\InvalidArgumentException::class);
		$this->source->countSharesByInitiator(new ShareReviewQuery(), 0);
	}

	public function testCountSharesByInitiatorDbErrorIsEmpty(): void {
		$this->aclMapper->method('countByInitiatorForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertSame([], $this->source->countSharesByInitiator(new ShareReviewQuery(), 5));
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

	/**
	 * Stand in for the listener: decide the access-check event, let the audit
	 * events the source dispatches around it through, and record everything.
	 */
	private function decideAccessCheck(callable $decide): void {
		$this->dispatched = [];
		$this->eventDispatcher->method('dispatchTyped')->willReturnCallback(
			function (object $event) use ($decide): void {
				$this->dispatched[] = $event;
				if ($event instanceof ShareReviewAccessCheckEvent) {
					$decide($event);
				}
			}
		);
	}

	/** @return list<array{0: string, 1: array}> message and parameters of every audit entry */
	private function auditEntries(): array {
		$entries = [];
		foreach ($this->dispatched as $event) {
			if ($event instanceof CriticalActionPerformedEvent) {
				$entries[] = [$event->getLogMessage(), $event->getParameters()];
			}
		}
		return $entries;
	}

	public function testDeleteShareEventDefaultsToAnOperatorDeletionBySessionUser(): void {
		$this->decideAccessCheck(static function (): void {
		});

		$this->assertFalse($this->source->deleteShare('7'));
		$event = $this->dispatched[0];
		$this->assertInstanceOf(ShareReviewAccessCheckEvent::class, $event);
		$this->assertSame('7', $event->getShareId());
		$this->assertSame(ShareReviewAccessCheckEvent::ACTION_DELETE, $event->getAction());
		$this->assertNull($event->getActingUserId());
		$this->assertSame(ShareReviewAccessCheckEvent::SCOPE_OPERATOR, $event->getScope());
	}

	public function testDeleteShareForwardsTheActionContextIntoTheEvent(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->denyAccess('not the initiator'));

		$this->source->deleteShare('7', new ShareReviewActionContext('alice', ShareReviewAccessCheckEvent::SCOPE_SELF));

		$event = $this->dispatched[0];
		$this->assertInstanceOf(ShareReviewAccessCheckEvent::class, $event);
		$this->assertSame('alice', $event->getActingUserId());
		$this->assertSame(ShareReviewAccessCheckEvent::SCOPE_SELF, $event->getScope());
	}

	public function testDeleteShareEventNotHandledReturnsFalseAndAuditsTheDenial(): void {
		$this->decideAccessCheck(static function (): void {
		});
		$this->boardService->expects($this->never())->method('deleteAclForShareReview');

		$this->assertFalse($this->source->deleteShare('7'));
		[$message, $parameters] = $this->auditEntries()[0];
		$this->assertStringContainsString('denied', $message);
		$this->assertSame(['7', ''], $parameters);
	}

	public function testDeleteShareEventDeniedReturnsFalse(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->denyAccess('not in group'));
		$this->boardService->expects($this->never())->method('deleteAclForShareReview');

		$this->assertFalse($this->source->deleteShare('7'));
		$this->assertCount(1, $this->auditEntries());
	}

	public function testDeleteShareEventGrantedReturnsTrueAndAudits(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($user);
		$this->boardService->expects($this->once())->method('deleteAclForShareReview')->with(7);

		$this->assertTrue($this->source->deleteShare('7'));
		[$message, $parameters] = $this->auditEntries()[0];
		$this->assertStringContainsString('deleted', $message);
		$this->assertCount(5, $parameters);
		$this->assertSame('7', $parameters[0]);
		$this->assertSame('admin', $parameters[4]);
	}

	public function testDeleteShareAuditsTheActingUserOfTheContextInsteadOfTheSession(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->userSession->expects($this->never())->method('getUser');

		$this->assertTrue($this->source->deleteShare('7', new ShareReviewActionContext('alice')));
		$this->assertSame('alice', $this->auditEntries()[0][1][4]);
	}

	public function testDeleteShareDoesNotExistReturnsFalseWithoutAuditingADeletion(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->boardService->expects($this->once())
			->method('deleteAclForShareReview')
			->willThrowException($this->createMock(DoesNotExistException::class));

		$this->assertFalse($this->source->deleteShare('7'));
		$this->assertSame([], $this->auditEntries());
	}
}
