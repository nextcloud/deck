<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

use OCA\Deck\Cron\CardDescriptionActivity;
use OCA\Deck\Cron\ScheduledNotifications;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\Event\AclUpdatedEvent;
use OCA\Deck\Event\BoardUpdatedEvent;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Service\AssignmentService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\StackService;
use OCP\Activity\IConsumer;
use OCP\Activity\IEvent as IActivityEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\App\IAppManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Server;
use OCP\Share\IShare;

/**
 * @group DB
 */
class BoardImporterTest extends \Test\TestCase {
	private const APP = 'import_test';
	private const OWNER = 'deck-import-service';
	private const ALICE = 'deck-import-alice';
	private const BOB = 'deck-import-bob';
	private const CAROL = 'deck-import-carol';
	private const GROUP = 'deck-import-planners';

	/** Counted before and after each import; none of them may grow */
	private const QUIET_TABLES = ['notifications', 'activity', 'activity_mq', 'comments', 'mail_queue'];

	private IBoardImporter $importer;
	private IDBConnection $db;
	private CardMapper $cardMapper;
	private string $boardRef;
	/** @var list<string> */
	private array $deckEvents = [];
	private int $activityEvents = 0;
	private int $mails = 0;
	private int $fileId;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		// The test bootstrap loads the server's own apps only; these deliver what the quiet test counts
		foreach (['deck', 'activity', 'notifications'] as $app) {
			Server::get(IAppManager::class)->loadApp($app);
		}

		$backend = new \Test\Util\User\Dummy();
		Server::get(IUserManager::class)->registerBackend($backend);
		foreach ([self::OWNER, self::ALICE, self::BOB, self::CAROL] as $uid) {
			$backend->createUser($uid, $uid);
		}
		$groupBackend = new \Test\Util\Group\Dummy();
		$groupBackend->createGroup(self::GROUP);
		$groupBackend->addToGroup(self::ALICE, self::GROUP);
		$groupBackend->addToGroup(self::BOB, self::GROUP);
		Server::get(IGroupManager::class)->addBackend($groupBackend);

		// The test cleanup empties the file cache but not the mount cache, which then points to storages of earlier runs
		$qb = Server::get(IDBConnection::class)->getQueryBuilder();
		$qb->delete('mounts')
			->where($qb->expr()->in('user_id', $qb->createNamedParameter([self::OWNER, self::ALICE, self::BOB, self::CAROL], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeStatement();
	}

	protected function setUp(): void {
		parent::setUp();
		$this->importer = Server::get(IBoardImporter::class);
		$this->db = Server::get(IDBConnection::class);
		$this->cardMapper = Server::get(CardMapper::class);
		$this->boardRef = 'plan-' . $this->getUniqueID();

		$folder = Server::get(IRootFolder::class)->getUserFolder(self::ALICE);
		$this->fileId = $folder->newFile('Budget ' . $this->getUniqueID() . '.txt', 'numbers')->getId();
		// Imports run without a user, like a bridge request
		Server::get(IUserSession::class)->setUser(null);

		$dispatcher = Server::get(IEventDispatcher::class);
		foreach ([CardCreatedEvent::class, CardUpdatedEvent::class, CardDeletedEvent::class, AclCreatedEvent::class,
			AclUpdatedEvent::class, AclDeletedEvent::class, BoardUpdatedEvent::class] as $eventClass) {
			$dispatcher->addListener($eventClass, function () use ($eventClass): void {
				$this->deckEvents[] = $eventClass;
			});
		}
		Server::get(IActivityManager::class)->registerConsumer(function () {
			return new class($this) implements IConsumer {
				public function __construct(
					private BoardImporterTest $test,
				) {
				}

				public function receive(IActivityEvent $event): void {
					$this->test->countActivity();
				}
			};
		});
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($this->createMock(IMessage::class));
		$mailer->method('send')->willReturnCallback(function () {
			$this->mails++;
			return [];
		});
		$this->overwriteService(IMailer::class, $mailer);
	}

	protected function tearDown(): void {
		$this->restoreService(IMailer::class);
		parent::tearDown();
	}

	public function countActivity(): void {
		$this->activityEvents++;
	}

	public function testEnsureBoardCreatesBoardLabelsAndStacksOnce(): void {
		$first = $this->ensureBoard();
		$this->assertSame(ImportResult::STATUS_OK, $first['status']);
		$this->assertTrue($first['created']);
		$this->assertSame(['category1', 'priority'], array_keys($first['labels']));
		$this->assertSame(['bucket-todo', 'bucket-done'], array_keys($first['stacks']));

		$board = Server::get(BoardMapper::class)->find($first['board_id']);
		$this->assertSame('Launch plan', $board->getTitle());
		$this->assertSame(self::OWNER, $board->getOwner());
		$this->assertSame('0082c9', $board->getColor());
		$this->assertCount(2, Server::get(LabelMapper::class)->findAll($first['board_id']), 'no default labels');
		$this->assertCount(2, Server::get(StackMapper::class)->findAll($first['board_id']));

		$again = $this->ensureBoard();
		$this->assertFalse($again['created']);
		$this->assertSame($first['board_id'], $again['board_id']);
		$this->assertSame($first['labels'], $again['labels']);
		$this->assertSame($first['stacks'], $again['stacks']);
		$this->assertSame([], $again['changed']);
		$this->assertCount(2, Server::get(LabelMapper::class)->findAll($first['board_id']));
	}

	public function testEnsureBoardUpdatesWhatNobodyChangedAndKeepsDeckChanges(): void {
		$board = $this->ensureBoard();
		$stackMapper = Server::get(StackMapper::class);
		$stack = $stackMapper->find($board['stacks']['bucket-todo']);
		$stack->setTitle('Renamed in Deck');
		$stackMapper->update($stack);

		$result = $this->ensureBoard('Launch plan 2026', [
			new ImportLabel('category1', 'Marketing', 'ff0000'),
			new ImportLabel('priority', 'Urgent', 'e9322d'),
			new ImportLabel('category2', 'Sales', '00ff00'),
		], [
			new ImportStack('bucket-todo', 'Backlog', 0),
			new ImportStack('bucket-done', 'Finished', 1),
		]);

		$this->assertSame(ImportResult::STATUS_OK, $result['status']);
		$this->assertSame(['bucket-todo'], $result['changed']);
		$this->assertSame('Launch plan 2026', Server::get(BoardMapper::class)->find($board['board_id'])->getTitle());
		$stackMapper = Server::get(StackMapper::class);
		$this->assertSame('Renamed in Deck', $stackMapper->find($board['stacks']['bucket-todo'])->getTitle());
		$this->assertSame('Finished', $stackMapper->find($board['stacks']['bucket-done'])->getTitle());
		$this->assertSame('ff0000', Server::get(LabelMapper::class)->find($board['labels']['category1'])->getColor());
		$this->assertArrayHasKey('category2', $result['labels']);
	}

	public function testEnsureBoardRefusesInvalidInput(): void {
		$result = $this->importer->ensureBoard(self::APP, $this->boardRef, 'nobody-' . $this->getUniqueID(), 'Plan', '0082c9',
			new \DateTimeImmutable(), [], []);
		$this->assertSame(ImportResult::STATUS_ERROR, $result['status']);
		$this->assertSame(ImportResult::ERROR_UNKNOWN_USER, $result['error']['code']);

		$result = $this->importer->ensureBoard(self::APP, $this->boardRef, self::OWNER, 'Plan', 'blue',
			new \DateTimeImmutable(), [], []);
		$this->assertSame(ImportResult::ERROR_INVALID, $result['error']['code']);

		$result = $this->importer->ensureBoard(self::APP, $this->boardRef, self::OWNER, 'Plan', '0082c9',
			new \DateTimeImmutable(), [new ImportLabel('a', 'Same', '000000'), new ImportLabel('b', 'Same', '111111')], []);
		$this->assertSame(ImportResult::ERROR_INVALID, $result['error']['code']);
	}

	public function testImportCardKeepsOriginalTimesAndRelations(): void {
		$board = $this->ensureBoard();
		$created = new \DateTimeImmutable('2025-11-03T09:15:00Z');
		$card = $this->card('task-1', [
			'description' => "Prepare the launch.\n\n- [ ] Draft\n- [x] Review\n\n[Spec](https://example.com/spec)",
			'start' => new \DateTimeImmutable('2025-11-04T08:00:00Z'),
			'due' => new \DateTimeImmutable('2025-11-20T17:00:00+01:00'),
			'done' => new \DateTimeImmutable('2025-11-19T12:30:45Z'),
			'created' => $created,
			'createdBy' => self::CAROL,
			'assignees' => [self::BOB, self::ALICE],
			'labels' => ['priority', 'category1'],
			'files' => [$this->fileId],
		]);

		[$result] = $this->importer->importCards(self::APP, $board['board_id'], [$card]);

		$this->assertSame(ImportResult::STATUS_OK, $result->status, (string)$result->errorMessage);
		$this->assertSame($card->sha256, $result->sha256, 'Deck hashes what it stored to what the importer computed');
		$stored = $this->cardMapper->find($result->cardId, false);
		$this->assertSame('Ship it', $stored->getTitle());
		$this->assertSame($card->description, $stored->getDescription());
		$this->assertSame($board['stacks']['bucket-todo'], $stored->getStackId());
		$this->assertSame($created->getTimestamp(), $stored->getCreatedAt());
		$this->assertSame('2025-11-19T12:30:45Z', CardCanonicalForm::time($stored->getDone()));
		$this->assertSame('2025-11-20T16:00:00Z', CardCanonicalForm::time($stored->getDuedate()));
		$this->assertSame('2025-11-04T08:00:00Z', CardCanonicalForm::time($stored->getStartdate()));
		$this->assertSame(self::CAROL, $stored->getOwner());
		$this->assertTrue($stored->getNotified(), 'a due date in the past is never announced');

		$assignees = array_map(fn ($a) => $a->getParticipant(), Server::get(AssignmentMapper::class)->findAll($result->cardId));
		sort($assignees);
		$this->assertSame([self::ALICE, self::BOB], $assignees);
		$labels = array_map(fn ($l) => $l->getId(), Server::get(LabelMapper::class)->findAssignedLabelsForCard($result->cardId));
		sort($labels);
		$expected = [$board['labels']['category1'], $board['labels']['priority']];
		sort($expected);
		$this->assertSame($expected, $labels);

		$shares = $this->deckShares($result->cardId);
		$this->assertCount(1, $shares);
		$this->assertSame($this->fileId, (int)$shares[0]['file_source']);
		$this->assertSame(self::ALICE, $shares[0]['uid_initiator'], 'the file owner shares the file with the card');
	}

	public function testRepeatedImportWritesNothing(): void {
		$board = $this->ensureBoard();
		$card = $this->card('task-1', ['assignees' => [self::ALICE], 'labels' => ['category1'], 'files' => [$this->fileId]]);
		[$first] = $this->importer->importCards(self::APP, $board['board_id'], [$card]);
		$qb = $this->db->getQueryBuilder();
		$qb->update('deck_cards')
			->set('last_modified', $qb->createNamedParameter(1000, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($first->cardId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		$rows = $this->rowCounts(['deck_cards', 'deck_assigned_users', 'deck_assigned_labels', 'share', 'deck_import_refs']);

		[$second] = $this->importer->importCards(self::APP, $board['board_id'], [$card]);

		$this->assertSame(ImportResult::STATUS_OK, $second->status);
		$this->assertSame($first->cardId, $second->cardId);
		$this->assertSame($first->sha256, $second->sha256);
		$this->assertSame($rows, $this->rowCounts(['deck_cards', 'deck_assigned_users', 'deck_assigned_labels', 'share', 'deck_import_refs']));
		$this->assertSame(1000, $this->cardMapper->find($first->cardId, false)->getLastModified(), 'the card was not written again');
	}

	public function testNewVersionUpdatesTheCard(): void {
		$board = $this->ensureBoard();
		$this->importer->importCards(self::APP, $board['board_id'], [
			$this->card('task-1', ['assignees' => [self::ALICE], 'labels' => ['category1'], 'files' => [$this->fileId]]),
		]);

		$next = $this->card('task-1', [
			'title' => 'Ship it on Monday',
			'stackRef' => 'bucket-done',
			'assignees' => [self::BOB],
			'labels' => ['priority'],
			'files' => [],
			'mode' => ImportCard::MODE_NEW_VERSION,
		]);
		[$result] = $this->importer->importCards(self::APP, $board['board_id'], [$next]);

		$this->assertSame(ImportResult::STATUS_OK, $result->status, (string)$result->errorMessage);
		$this->assertSame($next->sha256, $result->sha256);
		$stored = $this->cardMapper->find($result->cardId, false);
		$this->assertSame('Ship it on Monday', $stored->getTitle());
		$this->assertSame($board['stacks']['bucket-done'], $stored->getStackId());
		$this->assertSame([self::BOB], array_map(fn ($a) => $a->getParticipant(), Server::get(AssignmentMapper::class)->findAll($result->cardId)));
		$this->assertSame([], $this->deckShares($result->cardId));
	}

	public function testCreateOfAnExistingRefWithOtherContentConflicts(): void {
		$board = $this->ensureBoard();
		[$first] = $this->importer->importCards(self::APP, $board['board_id'], [$this->card('task-1')]);

		[$result] = $this->importer->importCards(self::APP, $board['board_id'], [$this->card('task-1', ['title' => 'Other'])]);

		$this->assertSame(ImportResult::STATUS_CONFLICT, $result->status);
		$this->assertSame(ImportResult::ERROR_EXISTS, $result->errorCode);
		$this->assertSame($first->cardId, $result->cardId);
		$this->assertSame('Ship it', $this->cardMapper->find($first->cardId, false)->getTitle());
	}

	public function testCardEditedInDeckIsReportedAndKept(): void {
		$board = $this->ensureBoard();
		[$first, $second] = $this->importer->importCards(self::APP, $board['board_id'], [$this->card('task-1'), $this->card('task-2')]);

		$card = $this->cardMapper->find($first->cardId, false);
		$card->setTitle('Edited by Alice');
		$this->cardMapper->update($card);
		$archived = $this->cardMapper->find($second->cardId, false);
		$archived->setArchived(true);
		$this->cardMapper->update($archived);

		$stats = $this->importer->statCards(self::APP, $board['board_id'], ['task-1', 'task-2', 'task-unknown'], true);
		$this->assertTrue($stats[0]->exists);
		$this->assertTrue($stats[0]->changed);
		$this->assertNotSame($first->sha256, $stats[0]->sha256);
		$this->assertTrue($stats[1]->changed, 'archiving is a change');
		$this->assertFalse($stats[2]->exists);

		[$result] = $this->importer->importCards(self::APP, $board['board_id'], [
			$this->card('task-1', ['title' => 'Newer in the source', 'mode' => ImportCard::MODE_NEW_VERSION]),
		]);
		$this->assertSame(ImportResult::STATUS_CONFLICT, $result->status);
		$this->assertSame(ImportResult::ERROR_CHANGED, $result->errorCode);
		$this->assertSame('Edited by Alice', $this->cardMapper->find($first->cardId, false)->getTitle());

		[$removed] = $this->importer->removeCards(self::APP, $board['board_id'], ['task-1']);
		$this->assertSame(ImportResult::ERROR_CHANGED, $removed->errorCode);
		$this->assertSame(0, $this->cardMapper->find($first->cardId, false)->getDeletedAt());
	}

	public function testUnchangedCardStatsWithoutHash(): void {
		$board = $this->ensureBoard();
		[$first] = $this->importer->importCards(self::APP, $board['board_id'], [$this->card('task-1')]);

		[$stat] = $this->importer->statCards(self::APP, $board['board_id'], ['task-1'], false);

		$this->assertEquals(new ImportCardStat('task-1', true, $first->cardId, null, false), $stat);
	}

	public function testRemoveCardsSoftDeletesAndCanRestore(): void {
		$board = $this->ensureBoard();
		[$first] = $this->importer->importCards(self::APP, $board['board_id'], [$this->card('task-1'), $this->card('task-2')]);

		$results = $this->importer->removeCards(self::APP, $board['board_id'], ['task-1', 'task-unknown']);

		$this->assertSame(ImportResult::STATUS_OK, $results[0]->status);
		$this->assertSame(ImportResult::ERROR_NOT_FOUND, $results[1]->errorCode);
		$this->assertGreaterThan(0, $this->cardMapper->find($first->cardId, false)->getDeletedAt());
		$deleted = array_map(fn ($c) => $c->getId(), $this->cardMapper->findDeleted($board['board_id']));
		$this->assertContains($first->cardId, $deleted, 'the card is in Deck\'s deleted cards');
		$this->assertSame(['task-2'], $this->importer->listCards(self::APP, $board['board_id'], '', 10)['entries']);
		$this->assertFalse($this->importer->statCards(self::APP, $board['board_id'], ['task-1'], false)[0]->exists);
		$this->assertSame(ImportResult::STATUS_OK, $this->importer->removeCards(self::APP, $board['board_id'], ['task-1'])[0]->status);

		[$restored] = $this->importer->importCards(self::APP, $board['board_id'], [$this->card('task-1')]);
		$this->assertSame(ImportResult::STATUS_OK, $restored->status);
		$this->assertSame($first->cardId, $restored->cardId);
		$this->assertSame(0, $this->cardMapper->find($first->cardId, false)->getDeletedAt());
	}

	public function testListCardsPages(): void {
		$board = $this->ensureBoard();
		$refs = ['task-a', 'task-b', 'task-c', 'task-d', 'task-e'];
		$this->importer->importCards(self::APP, $board['board_id'], array_map(fn ($ref) => $this->card($ref), $refs));

		$listed = [];
		$cursor = '';
		$pages = 0;
		do {
			$page = $this->importer->listCards(self::APP, $board['board_id'], $cursor, 2);
			$listed = array_merge($listed, $page['entries']);
			$cursor = $page['next_cursor'];
			$pages++;
		} while ($cursor !== '');

		$this->assertSame($refs, $listed);
		$this->assertSame(3, $pages);
	}

	public function testInvalidCardsAreRefusedAndWriteNothing(): void {
		$board = $this->ensureBoard();
		$rows = $this->rowCounts(['deck_cards', 'deck_import_refs', 'share']);

		$results = $this->importer->importCards(self::APP, $board['board_id'], [
			$this->card('a', ['stackRef' => 'bucket-unknown']),
			$this->card('b', ['labels' => ['unknown']]),
			$this->card('c', ['assignees' => ['nobody-' . $this->getUniqueID()]]),
			$this->card('d', ['createdBy' => 'nobody-' . $this->getUniqueID()]),
			$this->card('e', ['sha256' => 'ABC']),
			$this->card('f', ['mode' => ImportCard::MODE_NEW_VERSION]),
			$this->card('g', ['files' => [PHP_INT_MAX]]),
			$this->card('h', ['title' => '']),
			$this->card('i', ['assignees' => [self::ALICE, self::ALICE]]),
		]);

		$this->assertSame([
			ImportResult::ERROR_UNKNOWN_STACK,
			ImportResult::ERROR_UNKNOWN_LABEL,
			ImportResult::ERROR_UNKNOWN_USER,
			ImportResult::ERROR_UNKNOWN_USER,
			ImportResult::ERROR_INVALID,
			ImportResult::ERROR_NOT_FOUND,
			ImportResult::ERROR_FILE_UNAVAILABLE,
			ImportResult::ERROR_INVALID,
			ImportResult::ERROR_INVALID,
		], array_map(fn (ImportResult $r) => $r->errorCode, $results));
		$this->assertSame($rows, $this->rowCounts(['deck_cards', 'deck_import_refs', 'share']), 'a failed card leaves nothing behind');
	}

	public function testBoardsOfOthersAreRefused(): void {
		$this->actAs(self::ALICE);
		$own = Server::get(BoardService::class)->create('Alice\'s board', self::ALICE, '000000');
		$this->actAs(null);
		$imported = $this->ensureBoard();

		foreach ([
			[self::APP, $own->getId(), ImportException::NOT_IMPORTER],
			['other_app', $imported['board_id'], ImportException::NOT_IMPORTER],
			[self::APP, PHP_INT_MAX, ImportException::NOT_FOUND],
			['Not An App', $imported['board_id'], ImportException::INVALID],
		] as [$app, $boardId, $reason]) {
			try {
				$this->importer->importCards($app, $boardId, [$this->card('task-1')]);
				$this->fail('Expected ' . $reason);
			} catch (ImportException $e) {
				$this->assertSame($reason, $e->getReason());
			}
		}
	}

	public function testSetAccessManagesItsOwnShares(): void {
		$board = $this->ensureBoard();
		$aclMapper = Server::get(AclMapper::class);
		$acl = function (int $type, string $participant) use ($aclMapper, $board): ?Acl {
			foreach ($aclMapper->findAll($board['board_id']) as $acl) {
				if ($acl->getType() === $type && $acl->getParticipant() === $participant) {
					return $acl;
				}
			}
			return null;
		};
		$this->actAs(self::OWNER);
		Server::get(BoardService::class)->addAcl($board['board_id'], Acl::PERMISSION_TYPE_USER, self::CAROL, false, false, false);
		$this->actAs(null);

		$results = $this->importer->setAccess(self::APP, $board['board_id'], [
			new ImportAccess(ImportAccess::TYPE_GROUP, self::GROUP, true, false, false),
			new ImportAccess(ImportAccess::TYPE_USER, self::ALICE, true, true, true),
			new ImportAccess(ImportAccess::TYPE_USER, self::CAROL, true, true, true),
			new ImportAccess(ImportAccess::TYPE_GROUP, 'no-such-group', true, false, false),
		]);

		$this->assertSame([ImportResult::STATUS_OK, ImportResult::STATUS_OK, ImportResult::STATUS_CONFLICT, ImportResult::STATUS_ERROR],
			array_map(fn (ImportResult $r) => $r->status, $results));
		$this->assertSame(ImportResult::ERROR_EXISTS, $results[2]->errorCode);
		$this->assertSame(ImportResult::ERROR_UNKNOWN_GROUP, $results[3]->errorCode);
		$group = $acl(Acl::PERMISSION_TYPE_GROUP, self::GROUP);
		$this->assertTrue($group->getPermissionEdit());
		$this->assertFalse($group->getPermissionManage());
		$this->assertFalse($acl(Acl::PERMISSION_TYPE_USER, self::CAROL)->getPermissionEdit());

		$results = $this->importer->setAccess(self::APP, $board['board_id'], [
			new ImportAccess(ImportAccess::TYPE_GROUP, self::GROUP, true, true, false),
		]);

		$this->assertSame(ImportResult::STATUS_OK, $results[0]->status);
		$this->assertTrue($acl(Acl::PERMISSION_TYPE_GROUP, self::GROUP)->getPermissionShare());
		$this->assertNull($acl(Acl::PERMISSION_TYPE_USER, self::ALICE));
		$this->assertNotNull($acl(Acl::PERMISSION_TYPE_USER, self::CAROL), 'a share somebody else made stays');
	}

	/**
	 * A whole plan is imported and shared, then the cron jobs that announce
	 * due cards and description changes run: nobody hears about any of it.
	 */
	public function testImportIsQuiet(): void {
		$before = $this->rowCounts(self::QUIET_TABLES);

		$board = $this->ensureBoard();
		$results = $this->importer->importCards(self::APP, $board['board_id'], [
			$this->card('overdue', ['due' => new \DateTimeImmutable('-3 days'), 'assignees' => [self::ALICE, self::BOB], 'labels' => ['priority'], 'files' => [$this->fileId]]),
			$this->card('due-soon', ['due' => new \DateTimeImmutable('+1 hour'), 'assignees' => [self::BOB]]),
			$this->card('done', ['done' => new \DateTimeImmutable('-1 day'), 'assignees' => [self::ALICE]]),
		]);
		$results = array_merge($results, $this->importer->setAccess(self::APP, $board['board_id'], [
			new ImportAccess(ImportAccess::TYPE_GROUP, self::GROUP, true, false, false),
			new ImportAccess(ImportAccess::TYPE_USER, self::CAROL, true, true, true),
		]));
		$results = array_merge($results, $this->importer->importCards(self::APP, $board['board_id'], [
			$this->card('due-soon', ['title' => 'Moved', 'due' => new \DateTimeImmutable('-1 hour'), 'assignees' => [self::ALICE], 'mode' => ImportCard::MODE_NEW_VERSION]),
		]));
		$results = array_merge($results, $this->importer->removeCards(self::APP, $board['board_id'], ['done']));
		$results = array_merge($results, $this->importer->setAccess(self::APP, $board['board_id'], [
			new ImportAccess(ImportAccess::TYPE_GROUP, self::GROUP, true, true, false),
		]));
		$this->runJob(ScheduledNotifications::class);
		$this->runJob(CardDescriptionActivity::class);

		$this->assertSame(array_fill(0, 8, ImportResult::STATUS_OK), array_map(fn (ImportResult $r) => $r->status, $results));
		$this->assertCount(1, $this->deckShares($results[0]->cardId), 'the attachment was shared');
		$this->assertSame($before, $this->rowCounts(self::QUIET_TABLES));
		$this->assertSame([], $this->deckEvents, 'no Deck event reaches live updates or other apps');
		$this->assertSame(0, $this->activityEvents);
		$this->assertSame(0, $this->mails);
	}

	/**
	 * The same steps through Deck's services do announce themselves, so the
	 * quiet test would see them
	 */
	public function testProbesSeeDeckServices(): void {
		$before = $this->rowCounts(self::QUIET_TABLES);
		$this->actAs(self::OWNER);
		$board = Server::get(BoardService::class)->create('Visible', self::OWNER, '000000');
		Server::get(BoardService::class)->addAcl($board->getId(), Acl::PERMISSION_TYPE_USER, self::ALICE, true, false, false);
		$stack = Server::get(StackService::class)->create('To do', $board->getId(), 0);
		$card = Server::get(CardService::class)->create('Task', $stack->getId(), 'plain', 0, self::OWNER);
		Server::get(AssignmentService::class)->assignUser($card->getId(), self::ALICE);
		$this->actAs(null);

		$after = $this->rowCounts(self::QUIET_TABLES);
		foreach (['notifications', 'activity'] as $table) {
			if ($this->db->tableExists($table)) {
				$this->assertGreaterThan($before[$table], $after[$table], $table);
			}
		}
		$this->assertNotSame([], $this->deckEvents);
		$this->assertGreaterThan(0, $this->activityEvents);
	}

	/**
	 * Deck's services act for the user their permission service holds
	 */
	private function actAs(?string $uid): void {
		Server::get(IUserSession::class)->setUser($uid === null ? null : Server::get(IUserManager::class)->get($uid));
		Server::get(PermissionService::class)->setUserId($uid);
	}

	/**
	 * @param ?list<ImportLabel> $labels
	 * @param ?list<ImportStack> $stacks
	 */
	private function ensureBoard(string $title = 'Launch plan', ?array $labels = null, ?array $stacks = null): array {
		return $this->importer->ensureBoard(self::APP, $this->boardRef, self::OWNER, $title, '0082c9',
			new \DateTimeImmutable('2025-10-01T10:00:00Z'),
			$labels ?? [new ImportLabel('category1', 'Marketing', 'f1db50'), new ImportLabel('priority', 'Urgent', 'e9322d')],
			$stacks ?? [new ImportStack('bucket-todo', 'To do', 0), new ImportStack('bucket-done', 'Done', 1)],
		);
	}

	/**
	 * A card as an importing app sends it, with the hash it computes
	 */
	private function card(string $ref, array $fields = []): ImportCard {
		$fields += [
			'stackRef' => 'bucket-todo',
			'title' => 'Ship it',
			'description' => '',
			'order' => 0,
			'start' => null,
			'due' => null,
			'done' => null,
			'created' => new \DateTimeImmutable('2025-11-01T08:00:00Z'),
			'createdBy' => '',
			'assignees' => [],
			'labels' => [],
			'files' => [],
			'mode' => ImportCard::MODE_CREATE,
		];
		$fields['sha256'] ??= CardCanonicalForm::hash($ref, $fields['stackRef'], $fields['title'], $fields['description'],
			$fields['order'], $fields['start'], $fields['due'], $fields['done'], $fields['assignees'], $fields['labels'], $fields['files']);
		return new ImportCard($ref, $fields['stackRef'], $fields['title'], $fields['description'], $fields['order'],
			$fields['start'], $fields['due'], $fields['done'], $fields['created'], $fields['createdBy'],
			$fields['assignees'], $fields['labels'], $fields['files'], $fields['sha256'], $fields['mode']);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function deckShares(int $cardId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_DECK, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter((string)$cardId)));
		return $qb->executeQuery()->fetchAll();
	}

	/**
	 * @param list<string> $tables
	 * @return array<string, int>
	 */
	private function rowCounts(array $tables): array {
		$counts = [];
		foreach ($tables as $table) {
			if (!$this->db->tableExists($table)) {
				$counts[$table] = 0;
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*'))->from($table);
			$counts[$table] = (int)$qb->executeQuery()->fetchOne();
		}
		return $counts;
	}

	/**
	 * @param class-string<\OCP\BackgroundJob\TimedJob> $class
	 */
	private function runJob(string $class): void {
		$job = Server::get($class);
		$this->invokePrivate($job, 'run', [null]);
	}
}
