<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;

/**
 * Runs the search for a deleted participant against the real database, so
 * LIKE escaping and case rules of each backend are covered.
 *
 * @group DB
 */
class SwimlaneUserOrderCleanupTest extends \Test\TestCase {
	// The permission service keeps the user id of its first use, so all
	// database tests log in as the same user.
	private const TEST_USER = 'test-share-user1';

	private BoardService $boardService;
	private BoardMapper $boardMapper;
	/** @var list<int> */
	private array $boardIds = [];

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$backend = new \Test\Util\User\Dummy();
		Server::get(IUserManager::class)->registerBackend($backend);
		$backend->createUser(self::TEST_USER, self::TEST_USER);
	}

	public function setUp(): void {
		parent::setUp();
		Server::get(IUserSession::class)->login(self::TEST_USER, self::TEST_USER);
		$this->boardService = Server::get(BoardService::class);
		$this->boardMapper = Server::get(BoardMapper::class);
	}

	public function tearDown(): void {
		foreach ($this->boardIds as $boardId) {
			$this->boardService->deleteForce($boardId);
		}
		$this->boardIds = [];
		parent::tearDown();
	}

	/**
	 * One board whose user order holds the entry and the catch-all lane.
	 */
	private function createBoardWithEntry(string $entry): int {
		$board = $this->boardService->create('Swimlane cleanup', self::TEST_USER, '000000');
		$boardId = $board->getId();
		$this->boardIds[] = $boardId;
		$this->boardService->setSwimlaneSetting($boardId, 'swimlaneUserOrder', json_encode([$entry, '__none__'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		return $boardId;
	}

	private function storedOrder(int $boardId): ?string {
		$this->boardMapper->flushCache($boardId);
		return $this->boardMapper->find($boardId, false, false, true)->getSwimlaneUserOrder();
	}

	private function encodedEntry(string $entry): string {
		return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	public function testSearchTreatsLikeCharactersAsText(): void {
		$underscore = $this->createBoardWithEntry('0:a_n%');
		$plain = $this->createBoardWithEntry('0:ann');
		$slash = $this->createBoardWithEntry('1:Sales/EU');

		// _ and % are no wildcards, so 'ann' and the others do not match.
		$this->assertSame([$underscore], array_values(array_intersect(
			$this->boardMapper->findIdsWithSwimlaneUserOrderEntry($this->encodedEntry('0:a_n%')),
			[$underscore, $plain, $slash]
		)));
		// The slash is stored unescaped, the search has to find it.
		$this->assertSame([$slash], array_values(array_intersect(
			$this->boardMapper->findIdsWithSwimlaneUserOrderEntry($this->encodedEntry('1:Sales/EU')),
			[$underscore, $plain, $slash]
		)));
	}

	public function testRemovingAUserLeavesSimilarEntriesAlone(): void {
		$ann = $this->createBoardWithEntry('0:ann');
		$upper = $this->createBoardWithEntry('0:Ann');
		// The group id contains the text of the user entry.
		$group = $this->createBoardWithEntry('1:x"0:ann');
		$wildcard = $this->createBoardWithEntry('0:a_n%');
		$sameName = $this->createBoardWithEntry('1:ann');

		$found = $this->boardMapper->findIdsWithSwimlaneUserOrderEntry($this->encodedEntry('0:ann'));
		$this->assertContains($ann, $found);
		$this->assertNotContains($wildcard, $found);
		$this->assertNotContains($sameName, $found);

		$this->boardService->removeSwimlaneUserKey(Acl::PERMISSION_TYPE_USER, 'ann');

		$this->assertSame('["__none__"]', $this->storedOrder($ann));
		$this->assertSame('["0:Ann","__none__"]', $this->storedOrder($upper));
		$this->assertSame('["1:x\"0:ann","__none__"]', $this->storedOrder($group));
		$this->assertSame('["0:a_n%","__none__"]', $this->storedOrder($wildcard));
		$this->assertSame('["1:ann","__none__"]', $this->storedOrder($sameName));
	}

	public function testRemovingAUserWorksOnArchivedAndDeletedBoards(): void {
		$archived = $this->createBoardWithEntry('0:ann');
		$deleted = $this->createBoardWithEntry('0:ann');

		$board = $this->boardMapper->find($archived);
		$board->setArchived(true);
		$this->boardMapper->update($board);
		$board = $this->boardMapper->find($deleted);
		$board->setDeletedAt(time());
		$this->boardMapper->update($board);

		$this->boardService->removeSwimlaneUserKey(Acl::PERMISSION_TYPE_USER, 'ann');

		$this->assertSame('["__none__"]', $this->storedOrder($archived));
		$this->assertSame('["__none__"]', $this->storedOrder($deleted));
	}
}
