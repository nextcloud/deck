<?php

declare(strict_types=1);

namespace OCA\Deck\Tests\Unit\DAV;

use OCA\Deck\DAV\Calendar;
use OCA\Deck\DAV\DeckCalendarBackend;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use Sabre\DAV\Exception\Forbidden;
use Test\TestCase;

class CalendarTest extends TestCase {
	private function createBoard(): Board {
		$board = new Board();
		$board->setId(123);
		$board->setTitle('Board');
		$board->setColor('ff0000');
		$board->setLastModified(100);
		return $board;
	}

	public function testCalendarAclExposesWriteContentForEditors(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->method('checkBoardPermission')
			->willReturnMap([
				[123, Acl::PERMISSION_EDIT, true],
			]);

		$calendar = new Calendar('principals/users/user1', 'board-123', $this->createBoard(), $backend);
		$privileges = array_column($calendar->getACL(), 'privilege');

		$this->assertContains('{DAV:}read', $privileges);
		$this->assertContains('{DAV:}write-properties', $privileges);
		$this->assertContains('{DAV:}write-content', $privileges);
		$this->assertNotContains('{DAV:}write', $privileges);
		$this->assertNotContains('{DAV:}bind', $privileges);
		$this->assertNotContains('{DAV:}unbind', $privileges);
	}

	public function testCalendarAclCachesPermissionCheck(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->expects($this->once())
			->method('checkBoardPermission')
			->with(123, Acl::PERMISSION_EDIT)
			->willReturn(true);

		$calendar = new Calendar('principals/users/user1', 'board-123', $this->createBoard(), $backend);

		$this->assertSame($calendar->getACL(), $calendar->getACL());
	}

	public function testCalendarIsNotSharedForDavSchedulePlugin(): void {
		$calendar = new Calendar(
			'principals/users/user1',
			'board-123',
			$this->createBoard(),
			$this->createMock(DeckCalendarBackend::class)
		);

		$this->assertFalse($calendar->isShared());
	}

	public function testCalendarAclDoesNotExposeWriteContentForReadOnlyUsers(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->method('checkBoardPermission')
			->willReturnMap([
				[123, Acl::PERMISSION_EDIT, false],
			]);

		$calendar = new Calendar('principals/users/user1', 'board-123', $this->createBoard(), $backend);
		$privileges = array_column($calendar->getACL(), 'privilege');

		$this->assertContains('{DAV:}read', $privileges);
		$this->assertContains('{DAV:}write-properties', $privileges);
		$this->assertNotContains('{DAV:}write-content', $privileges);
	}

	public function testCreateFileStaysForbidden(): void {
		$calendar = new Calendar(
			'principals/users/user1',
			'board-123',
			$this->createBoard(),
			$this->createMock(DeckCalendarBackend::class)
		);

		$this->expectException(Forbidden::class);
		$calendar->createFile('client-generated.ics', "BEGIN:VCALENDAR\r\nEND:VCALENDAR");
	}
}
