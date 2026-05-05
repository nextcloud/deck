<?php

declare(strict_types=1);

namespace OCA\Deck\Tests\Unit\DAV;

use OCA\Deck\BadRequestException;
use OCA\Deck\DAV\Calendar;
use OCA\Deck\DAV\CalendarObject;
use OCA\Deck\DAV\DeckCalendarBackend;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
use OCA\Deck\NoPermissionException;
use OCA\Deck\StatusException;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Test\TestCase;

class CalendarObjectTest extends TestCase {
	private function createCard(int $id = 1): Card {
		$card = new Card();
		$card->setId($id);
		$card->setTitle('Card');
		$card->setDescription('Description');
		$card->setStackId(10);
		$card->setType('plain');
		$card->setOwner('user1');
		$card->setOrder(1);
		$card->setCreatedAt(100);
		$card->setLastModified(200);
		$card->setArchived(false);
		$card->setDone(null);
		return $card;
	}

	private function createStack(): Stack {
		$stack = new Stack();
		$stack->setId(10);
		$stack->setTitle('Stack');
		$stack->setBoardId(123);
		$stack->setLastModified(200);
		return $stack;
	}

	/**
	 * @return Calendar&MockObject
	 */
	private function createCalendarMock(): Calendar {
		$calendar = $this->getMockBuilder(Calendar::class)
			->disableOriginalConstructor()
			->onlyMethods(['getACL', 'getOwner', 'getGroup'])
			->getMock();
		$calendar->method('getACL')->willReturn([
			[
				'privilege' => '{DAV:}read',
				'principal' => 'principals/users/user1',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}write-content',
				'principal' => 'principals/users/user1',
				'protected' => true,
			],
		]);
		$calendar->method('getOwner')->willReturn('principals/users/user1');
		$calendar->method('getGroup')->willReturn([]);
		return $calendar;
	}

	public function testPutUpdatesCard(): void {
		$calendar = $this->createCalendarMock();
		$sourceCard = $this->createCard();
		$updatedCard = $this->createCard();
		$updatedCard->setLastModified(300);

		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->expects($this->once())
			->method('updateCardFromCalendarObject')
			->with($sourceCard, "BEGIN:VCALENDAR\r\nEND:VCALENDAR")
			->willReturn($updatedCard);

		$object = new CalendarObject($calendar, 'card-1.ics', $backend, $sourceCard);
		$object->put("BEGIN:VCALENDAR\r\nEND:VCALENDAR");

		$this->assertSame(300, $object->getLastModified());
	}

	public function testPutReadsResourcePayload(): void {
		$calendar = $this->createCalendarMock();
		$sourceCard = $this->createCard();
		$updatedCard = $this->createCard();
		$updatedCard->setLastModified(300);
		$payload = "BEGIN:VCALENDAR\r\nEND:VCALENDAR";
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, $payload);
		rewind($stream);

		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->expects($this->once())
			->method('updateCardFromCalendarObject')
			->with($sourceCard, $payload)
			->willReturn($updatedCard);

		$object = new CalendarObject($calendar, 'card-1.ics', $backend, $sourceCard);
		$object->put($stream);
		fclose($stream);

		$this->assertSame(300, $object->getLastModified());
	}

	public function testPutRefreshesSerializedObjectAndKeepsEtagStableForNextGet(): void {
		$calendar = $this->createCalendarMock();
		$sourceCard = $this->createCard();
		$updatedCard = $this->createCard();
		$updatedCard->setTitle('Updated card');
		$updatedCard->setLastModified(300);

		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->method('updateCardFromCalendarObject')->willReturn($updatedCard);

		$object = new CalendarObject($calendar, 'card-1.ics', $backend, $sourceCard);
		$object->put("BEGIN:VCALENDAR\r\nEND:VCALENDAR");

		$etag = $object->getETag();
		$serialized = $object->get();

		$this->assertSame($etag, $object->getETag());
		$this->assertStringContainsString('SUMMARY:Updated card', $serialized);
	}

	public function testStackPutIsForbidden(): void {
		$object = new CalendarObject(
			$this->createCalendarMock(),
			'stack-10.ics',
			$this->createMock(DeckCalendarBackend::class),
			$this->createStack()
		);

		$this->expectException(Forbidden::class);
		$object->put("BEGIN:VCALENDAR\r\nEND:VCALENDAR");
	}

	public function testDeleteStaysForbiddenForCards(): void {
		$object = new CalendarObject(
			$this->createCalendarMock(),
			'card-1.ics',
			$this->createMock(DeckCalendarBackend::class),
			$this->createCard()
		);

		$this->expectException(Forbidden::class);
		$object->delete();
	}

	public function testStackAclDoesNotExposeWriteContent(): void {
		$object = new CalendarObject(
			$this->createCalendarMock(),
			'stack-10.ics',
			$this->createMock(DeckCalendarBackend::class),
			$this->createStack()
		);

		$privileges = array_column($object->getACL(), 'privilege');
		$this->assertContains('{DAV:}read', $privileges);
		$this->assertNotContains('{DAV:}write-content', $privileges);
	}

	public function testPutMapsNoPermissionExceptionToForbidden(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->method('updateCardFromCalendarObject')
			->willThrowException(new NoPermissionException('No edit permission'));

		$object = new CalendarObject(
			$this->createCalendarMock(),
			'card-1.ics',
			$backend,
			$this->createCard()
		);

		$this->expectException(Forbidden::class);
		$object->put("BEGIN:VCALENDAR\r\nEND:VCALENDAR");
	}

	public function testPutMapsStatusExceptionToForbidden(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->method('updateCardFromCalendarObject')
			->willThrowException(new StatusException('Operation not allowed. This board is archived.'));

		$object = new CalendarObject(
			$this->createCalendarMock(),
			'card-1.ics',
			$backend,
			$this->createCard()
		);

		$this->expectException(Forbidden::class);
		$object->put("BEGIN:VCALENDAR\r\nEND:VCALENDAR");
	}

	public function testPutMapsBadRequestExceptionToBadRequest(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->method('updateCardFromCalendarObject')
			->willThrowException(new BadRequestException('Invalid card data'));

		$object = new CalendarObject(
			$this->createCalendarMock(),
			'card-1.ics',
			$backend,
			$this->createCard()
		);

		$this->expectException(BadRequest::class);
		$object->put("BEGIN:VCALENDAR\r\nEND:VCALENDAR");
	}

	public function testPutMapsDoesNotExistExceptionToNotFound(): void {
		$backend = $this->createMock(DeckCalendarBackend::class);
		$backend->method('updateCardFromCalendarObject')
			->willThrowException(new DoesNotExistException('Card not found'));

		$object = new CalendarObject(
			$this->createCalendarMock(),
			'card-1.ics',
			$backend,
			$this->createCard()
		);

		$this->expectException(NotFound::class);
		$object->put("BEGIN:VCALENDAR\r\nEND:VCALENDAR");
	}
}
