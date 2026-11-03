<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Import;

use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCP\App\IAppManager;
use OCP\Comments\ICommentsManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;

/**
 * The board reader against the real database: a person reads the boards
 * they have, whole, and reading changes nothing.
 *
 * @group DB
 */
class BoardReaderTest extends \Test\TestCase {
	private const APP = 'import_test';
	private const OWNER = 'deck-read-owner';
	private const ALICE = 'deck-read-alice';
	private const BOB = 'deck-read-bob';
	private const CAROL = 'deck-read-carol';
	private const GROUP = 'deck-read-team';

	/** Counted before and after reading; none of them may change */
	private const QUIET_TABLES = ['notifications', 'activity', 'activity_mq', 'comments', 'comments_read_markers', 'deck_sessions', 'share', 'deck_boards', 'deck_cards', 'deck_attachment', 'filecache'];

	private IBoardReader $reader;
	private IBoardImporter $importer;
	private IDBConnection $db;
	private int $boardId;
	/** @var array<string, int> card ID by ref */
	private array $cards = [];
	private int $fileId;
	private string $fileName;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
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
		$groupBackend->addToGroup(self::BOB, self::GROUP);
		Server::get(IGroupManager::class)->addBackend($groupBackend);

		$qb = Server::get(IDBConnection::class)->getQueryBuilder();
		$qb->delete('mounts')
			->where($qb->expr()->in('user_id', $qb->createNamedParameter([self::OWNER, self::ALICE, self::BOB, self::CAROL], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeStatement();
	}

	protected function setUp(): void {
		parent::setUp();
		$this->reader = Server::get(IBoardReader::class);
		$this->importer = Server::get(IBoardImporter::class);
		$this->db = Server::get(IDBConnection::class);
		Server::get(IUserSession::class)->setUser(null);

		$this->fileName = 'Budget ' . $this->getUniqueID() . '.txt';
		$this->fileId = Server::get(IRootFolder::class)->getUserFolder(self::OWNER)->newFile($this->fileName, 'numbers')->getId();

		$board = $this->importer->ensureBoard(self::APP, 'read-' . $this->getUniqueID(), self::OWNER, 'Launch plan', '0082c9',
			new \DateTimeImmutable('2025-10-01T10:00:00Z'),
			[new ImportLabel('urgent', 'Urgent', 'e9322d'), new ImportLabel('later', 'Later', 'f1db50')],
			[new ImportStack('todo', 'To do', 0), new ImportStack('done', 'Done', 1)],
		);
		$this->assertSame(ImportResult::STATUS_OK, $board['status']);
		$this->boardId = $board['board_id'];

		$results = $this->importer->importCards(self::APP, $this->boardId, [
			$this->card('task', 'todo', 'Ship it', 0, ['assignees' => [self::ALICE], 'labels' => ['urgent'], 'files' => [$this->fileId],
				'due' => new \DateTimeImmutable('2025-11-20T16:00:00Z')]),
			$this->card('second', 'todo', 'Second', 1),
			$this->card('finished', 'done', 'Finished', 0, ['done' => new \DateTimeImmutable('2025-11-19T12:00:00Z')]),
			$this->card('gone', 'done', 'Removed', 1),
		]);
		foreach (['task', 'second', 'finished', 'gone'] as $i => $ref) {
			$this->assertSame(ImportResult::STATUS_OK, $results[$i]->status, (string)$results[$i]->errorMessage);
			$this->cards[$ref] = (int)$results[$i]->cardId;
		}
		$this->importer->removeCards(self::APP, $this->boardId, ['gone']);
		$this->importer->setAccess(self::APP, $this->boardId, [
			new ImportAccess(ImportAccess::TYPE_USER, self::ALICE, true, false, false),
			new ImportAccess(ImportAccess::TYPE_GROUP, self::GROUP, false, false, false),
		]);

		$cardMapper = Server::get(CardMapper::class);
		$second = $cardMapper->find($this->cards['second'], false);
		$second->setArchived(true);
		$cardMapper->update($second);

		$comments = Server::get(ICommentsManager::class);
		foreach (['First comment', 'Second comment'] as $i => $text) {
			$comment = $comments->create('users', self::BOB, 'deckCard', (string)$this->cards['task']);
			$comment->setMessage($text);
			$comment->setVerb('comment');
			$comment->setCreationDateTime(new \DateTime('2025-11-0' . ($i + 2) . 'T09:00:00Z'));
			$comments->save($comment);
		}

		$attachment = new Attachment();
		$attachment->setCardId($this->cards['task']);
		$attachment->setType(IBoardReader::ATTACHMENT_DECK_FILE);
		$attachment->setData('legacy.pdf');
		$attachment->setCreatedBy(self::OWNER);
		$attachment->setLastModified(time());
		$attachment->setCreatedAt(time());
		Server::get(AttachmentMapper::class)->insert($attachment);
		Server::get(BoardMapper::class)->flushCache();
	}

	protected function tearDown(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('comments')
			->where($qb->expr()->eq('object_type', $qb->createNamedParameter('deckCard')))
			->andWhere($qb->expr()->in('object_id', $qb->createNamedParameter(array_map('strval', array_values($this->cards)), IQueryBuilder::PARAM_STR_ARRAY)))
			->executeStatement();
		parent::tearDown();
	}

	public function testBoardsOwnedOrSharedDirectlyOrThroughAGroup(): void {
		foreach ([self::OWNER, self::ALICE, self::BOB] as $uid) {
			$boards = array_values(array_filter($this->reader->boards($uid), fn (array $b): bool => $b['id'] === $this->boardId));
			$this->assertCount(1, $boards, $uid);
			$this->assertSame('Launch plan', $boards[0]['title']);
			$this->assertSame(self::OWNER, $boards[0]['owner']);
			$this->assertSame(3, $boards[0]['cards'], 'the removed card is not counted');
		}
		$this->assertSame([], array_filter($this->reader->boards(self::CAROL), fn (array $b): bool => $b['id'] === $this->boardId));
		$this->assertSame([], $this->reader->boards('deck-read-nobody'));
	}

	public function testABoardWithCardsCommentsAndAttachments(): void {
		$board = $this->reader->board(self::BOB, $this->boardId);
		$this->assertSame('0082c9', $board['color']);
		$this->assertSame(['Urgent', 'Later'], array_map(static fn (array $l): string => $l['title'], $board['labels']));
		$this->assertSame(['To do', 'Done'], array_map(static fn (array $s): string => $s['title'], $board['stacks']));

		[$todo, $done] = $board['stacks'];
		$this->assertSame(['Ship it', 'Second'], array_map(static fn (array $c): string => $c['title'], $todo['cards']));
		$this->assertSame(['Finished'], array_map(static fn (array $c): string => $c['title'], $done['cards']), 'removed cards are left out');
		$this->assertTrue($todo['cards'][1]['archived']);
		$this->assertSame(strtotime('2025-11-19T12:00:00Z'), $done['cards'][0]['done']);

		$task = $todo['cards'][0];
		$this->assertSame(strtotime('2025-11-20T16:00:00Z'), $task['due']);
		$urgent = array_values(array_filter($board['labels'], static fn (array $l): bool => $l['title'] === 'Urgent'))[0];
		$this->assertSame([$urgent['id']], $task['labels']);
		$this->assertSame([['type' => 'user', 'id' => self::ALICE, 'display_name' => self::ALICE]], $task['assignees']);
		$this->assertSame(['First comment', 'Second comment'], array_map(static fn (array $c): string => $c['text'], $task['comments']));
		$this->assertSame(self::BOB, $task['comments'][0]['actor_id']);
		$this->assertSame(strtotime('2025-11-02T09:00:00Z'), $task['comments'][0]['created']);

		$this->assertCount(2, $task['attachments']);
		$this->assertSame(['type' => 'file', 'file_id' => $this->fileId, 'name' => $this->fileName, 'size' => 7], $task['attachments'][0]);
		$this->assertSame('deck_file', $task['attachments'][1]['type']);
		$this->assertSame('legacy.pdf', $task['attachments'][1]['name']);
	}

	public function testOnlyThePersonsBoards(): void {
		foreach ([[self::CAROL, $this->boardId], [self::BOB, 0], [self::BOB, $this->boardId + 100000]] as [$uid, $id]) {
			try {
				$this->reader->board($uid, $id);
				$this->fail('read board ' . $id . ' as ' . $uid);
			} catch (ImportException $e) {
				$this->assertSame(ImportException::NOT_FOUND, $e->getReason());
			}
		}
	}

	public function testReadingChangesNothing(): void {
		$before = $this->rows();
		$events = [];
		$listener = function (Event $event) use (&$events): void {
			$events[] = $event::class;
		};
		$dispatcher = Server::get(IEventDispatcher::class);
		$watched = [
			\OCA\Deck\Event\CardUpdatedEvent::class,
			\OCA\Deck\Event\BoardUpdatedEvent::class,
			\OCA\Deck\Event\SessionCreatedEvent::class,
			\OCP\Comments\CommentsEntityEvent::class,
		];
		foreach ($watched as $class) {
			$dispatcher->addListener($class, $listener);
		}

		foreach ([self::OWNER, self::ALICE, self::BOB] as $uid) {
			$this->reader->boards($uid);
			$this->reader->board($uid, $this->boardId);
		}

		foreach ($watched as $class) {
			$dispatcher->removeListener($class, $listener);
		}
		$this->assertSame([], $events);
		$this->assertSame($before, $this->rows());
	}

	/**
	 * Rows and last changes of the tables reading could touch
	 *
	 * @return array<string, mixed>
	 */
	private function rows(): array {
		$out = [];
		foreach (self::QUIET_TABLES as $table) {
			if (!$this->db->tableExists($table)) {
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*'))->from($table);
			$result = $qb->executeQuery();
			$out[$table] = (int)$result->fetchOne();
			$result->closeCursor();
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'last_modified', 'archived')->from('deck_cards')
			->where($qb->expr()->in('id', $qb->createNamedParameter(array_values($this->cards), IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id');
		$result = $qb->executeQuery();
		$out['cards'] = $result->fetchAll();
		$result->closeCursor();
		$out['board'] = Server::get(BoardMapper::class)->find($this->boardId)->getLastModified();
		return $out;
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private function card(string $ref, string $stackRef, string $title, int $order, array $fields = []): ImportCard {
		$fields += ['due' => null, 'done' => null, 'assignees' => [], 'labels' => [], 'files' => []];
		$hash = CardCanonicalForm::hash($ref, $stackRef, $title, '', $order, null, $fields['due'], $fields['done'],
			$fields['assignees'], $fields['labels'], $fields['files']);
		return new ImportCard($ref, $stackRef, $title, '', $order, null, $fields['due'], $fields['done'],
			new \DateTimeImmutable('2025-11-01T08:00:00Z'), '', $fields['assignees'], $fields['labels'], $fields['files'], $hash);
	}
}
