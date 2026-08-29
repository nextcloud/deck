<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use OCP\Share\IShare;
use OCP\Share\ShareReview\ShareReviewQuery;
use Test\TestCase;

/**
 * Runs the share-review page/count queries against the real database, so the
 * SQL translation of the ShareReviewQuery contract (sorting, search, filters,
 * counts, LIKE escaping) is verified on every supported database engine.
 *
 * @group DB
 */
class AclMapperShareReviewTest extends TestCase {
	private IDBConnection $db;
	private AclMapper $aclMapper;
	/** @var list<int> */
	private array $boardIds = [];
	/** @var array<string, int> participant to acl id */
	private array $aclIds = [];

	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->aclMapper = new AclMapper($this->db);

		$budget = $this->insertBoard('Budget', 'alice');
		$zeta = $this->insertBoard('Zeta plans', 'bob');
		$this->insertAcl($budget, Acl::PERMISSION_TYPE_USER, 'bob', edit: true, at: 500);
		$this->insertAcl($budget, Acl::PERMISSION_TYPE_GROUP, 'devs', manage: true, at: 300);
		$this->insertAcl($zeta, Acl::PERMISSION_TYPE_USER, 'alice', at: 200);
		$this->insertAcl($zeta, Acl::PERMISSION_TYPE_CIRCLE, 'teamX', share: true, at: 400);
		// predates the timestamp columns: both 0
		$this->insertAcl($zeta, Acl::PERMISSION_TYPE_USER, 'carol', at: 0);
	}

	public function tearDown(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_board_acl')->where($qb->expr()->in('board_id', $qb->createNamedParameter($this->boardIds, IQueryBuilder::PARAM_INT_ARRAY)))->executeStatement();
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_boards')->where($qb->expr()->in('id', $qb->createNamedParameter($this->boardIds, IQueryBuilder::PARAM_INT_ARRAY)))->executeStatement();
		parent::tearDown();
	}

	private function insertBoard(string $title, string $owner, int $deletedAt = 0): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('deck_boards')->values([
			'title' => $qb->createNamedParameter($title),
			'owner' => $qb->createNamedParameter($owner),
			'color' => $qb->createNamedParameter('000000'),
			'deleted_at' => $qb->createNamedParameter($deletedAt, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		$id = $qb->getLastInsertId();
		$this->boardIds[] = $id;
		return $id;
	}

	public function testTrashedBoardsAreNotReviewable(): void {
		$before = $this->aclMapper->countForShareReview(new ShareReviewQuery())->totalCount;
		$trashed = $this->insertBoard('Trashed', 'alice', deletedAt: 1700000000);
		$this->insertAcl($trashed, Acl::PERMISSION_TYPE_USER, 'mallory', at: 600);

		$counts = $this->aclMapper->countForShareReview(new ShareReviewQuery());
		$rows = $this->aclMapper->findPageForShareReview(new ShareReviewQuery(limit: 500));

		self::assertSame($before, $counts->totalCount, 'the trashed board\'s ACL is not counted');
		self::assertNotContains('mallory', array_column($rows, 'participant'));

		$qb = $this->db->getQueryBuilder();
		$aclId = (int)$qb->select('id')->from('deck_board_acl')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($trashed, IQueryBuilder::PARAM_INT)))
			->executeQuery()->fetchOne();
		self::assertNull($this->aclMapper->findForShareReview($aclId), 'the by-id lookup shares the exclusion');
	}

	private function insertAcl(int $boardId, int $type, string $participant, bool $edit = false, bool $share = false, bool $manage = false, int $at = 0): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('deck_board_acl')->values([
			'board_id' => $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT),
			'type' => $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT),
			'participant' => $qb->createNamedParameter($participant),
			'permission_edit' => $qb->createNamedParameter($edit, IQueryBuilder::PARAM_BOOL),
			'permission_share' => $qb->createNamedParameter($share, IQueryBuilder::PARAM_BOOL),
			'permission_manage' => $qb->createNamedParameter($manage, IQueryBuilder::PARAM_BOOL),
			'created_at' => $qb->createNamedParameter($at, IQueryBuilder::PARAM_INT),
			'last_modified_at' => $qb->createNamedParameter($at, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		$this->aclIds[$participant] = $qb->getLastInsertId();
	}

	/**
	 * Only the fixture rows, in page order (other tests may leave ACLs behind).
	 *
	 * @return list<string>
	 */
	private function participantsOf(ShareReviewQuery $query, ?array $types = null, ?array $permissions = null): array {
		$rows = $this->aclMapper->findPageForShareReview($query, $types, $permissions);
		$own = array_filter($rows, fn (array $row): bool => in_array((int)$row['board_id'], $this->boardIds, true));
		return array_values(array_map(static fn (array $row): string => (string)$row['participant'], $own));
	}

	private function filteredCount(ShareReviewQuery $query, ?array $types = null, ?array $permissions = null): int {
		// scope every count to the fixture boards through the initiator filter
		$scoped = new ShareReviewQuery(...array_merge(get_object_vars($query), [
			'initiatorIds' => $query->initiatorIds ?? ['alice', 'bob'],
		]));
		return $this->aclMapper->countForShareReview($scoped, $types, $permissions)->filteredCount;
	}

	public function testDefaultSortIsTimeDescendingWithIdTiebreakerAndZeroTimesLast(): void {
		$this->assertSame(['bob', 'teamX', 'devs', 'alice', 'carol'], $this->participantsOf(new ShareReviewQuery(limit: 500, initiatorIds: ['alice', 'bob'])));
		$this->assertSame(['carol', 'alice', 'devs', 'teamX', 'bob'], $this->participantsOf(new ShareReviewQuery(limit: 500, initiatorIds: ['alice', 'bob'], sortDescending: false)));
	}

	public function testSortByObjectInitiatorRecipientAndType(): void {
		$base = ['limit' => 500, 'initiatorIds' => ['alice', 'bob'], 'sortDescending' => false];
		$this->assertSame(['bob', 'devs', 'alice', 'teamX', 'carol'], $this->participantsOf(new ShareReviewQuery(...$base, sortField: ShareReviewQuery::SORT_OBJECT)));
		$this->assertSame(['bob', 'devs', 'alice', 'teamX', 'carol'], $this->participantsOf(new ShareReviewQuery(...$base, sortField: ShareReviewQuery::SORT_INITIATOR)));
		$this->assertSame(['alice', 'bob', 'carol', 'devs', 'teamX'], $this->participantsOf(new ShareReviewQuery(...$base, sortField: ShareReviewQuery::SORT_RECIPIENT)));
		// type 0 user (ids ascending), 1 group, 7 circle
		$this->assertSame(['bob', 'alice', 'carol', 'devs', 'teamX'], $this->participantsOf(new ShareReviewQuery(...$base, sortField: ShareReviewQuery::SORT_TYPE)));
	}

	public function testPagination(): void {
		$base = ['initiatorIds' => ['alice', 'bob']];
		$this->assertSame(['bob', 'teamX'], $this->participantsOf(new ShareReviewQuery(...$base, limit: 2)));
		$this->assertSame(['devs', 'alice'], $this->participantsOf(new ShareReviewQuery(...$base, limit: 2, offset: 2)));
		$this->assertSame(['carol'], $this->participantsOf(new ShareReviewQuery(...$base, limit: 2, offset: 4)));
	}

	public function testSearchSpansTitleOwnerAndParticipantCaseInsensitively(): void {
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(search: 'ZETA')));
		// owner bob (3 Zeta ACLs) plus participant bob
		$this->assertSame(4, $this->filteredCount(new ShareReviewQuery(search: 'bob')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(search: '%')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(search: '_')));
	}

	public function testObjectSearchAnyMatchesAnyOfThePatterns(): void {
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['BUDGET', 'zeta'])));
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['zeta', 'nomatch'])));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['zeta'], objectSearch: 'budget')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(objectSearchAny: [])));
	}

	public function testScopedFiltersAndIdLists(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(objectSearch: 'budget')));
		$this->assertSame(1, $this->filteredCount(new ShareReviewQuery(recipientSearch: 'team')));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(initiatorIds: ['alice'])));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(initiatorSearch: 'bo', initiatorIds: ['alice'])));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(recipientIds: ['bob', 'devs'])));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(recipientIds: [])));
	}

	public function testTypeAndPermissionFilters(): void {
		$query = new ShareReviewQuery();
		$this->assertSame(3, $this->filteredCount($query, [Acl::PERMISSION_TYPE_USER]));
		$this->assertSame(2, $this->filteredCount($query, [Acl::PERMISSION_TYPE_GROUP, Acl::PERMISSION_TYPE_CIRCLE]));
		$this->assertSame(0, $this->filteredCount($query, []));
		$this->assertSame(1, $this->filteredCount($query, null, ['permission_manage']));
		$this->assertSame(2, $this->filteredCount($query, null, ['permission_edit', 'permission_share']));
		$this->assertSame(0, $this->filteredCount($query, null, []));
	}

	public function testPasswordAndExpirationFiltersAreConstantFalse(): void {
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(hasPassword: true)));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(hasPassword: false)));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(hasExpiration: true)));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(expiresBeforeTimestamp: PHP_INT_MAX)));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(hasExpiration: false)));
	}

	public function testModifiedSinceIsStrictOnTheTimeExpression(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(modifiedSinceTimestamp: 300)));
		$this->assertSame(4, $this->filteredCount(new ShareReviewQuery(modifiedSinceTimestamp: 0)));
	}

	public function testCountByTypeAppliesFiltersAndOmitsZeroCounts(): void {
		$byType = $this->aclMapper->countByTypeForShareReview(new ShareReviewQuery(initiatorIds: ['alice', 'bob']));
		ksort($byType);
		$this->assertSame([Acl::PERMISSION_TYPE_USER => 3, Acl::PERMISSION_TYPE_GROUP => 1, Acl::PERMISSION_TYPE_CIRCLE => 1], $byType);
	}

	public function testFindForShareReviewCarriesTheBoardColumns(): void {
		$row = $this->aclMapper->findForShareReview($this->aclIds['teamX']);

		$this->assertNotNull($row);
		$this->assertSame('teamX', $row['participant']);
		$this->assertSame('Zeta plans', $row['board_title']);
		$this->assertSame('bob', $row['board_owner']);
		$this->assertNull($this->aclMapper->findForShareReview(0));
	}

	public function testTotalCountIgnoresFilters(): void {
		$counts = $this->aclMapper->countForShareReview(new ShareReviewQuery(initiatorIds: ['alice']));

		$this->assertGreaterThanOrEqual(5, $counts->totalCount);
		$this->assertSame(2, $counts->filteredCount);
	}
}
