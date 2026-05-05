<?php

declare(strict_types=1);

namespace OCA\Deck\Tests\Unit\DAV;

use OCA\Deck\DAV\DeckCalendarBackend;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\StackService;
use Sabre\VObject\InvalidDataException;
use Test\TestCase;

class DeckCalendarBackendTest extends TestCase {
	private CardService $cardService;
	private PermissionService $permissionService;
	private DeckCalendarBackend $backend;

	protected function setUp(): void {
		parent::setUp();

		$this->cardService = $this->createMock(CardService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->backend = new DeckCalendarBackend(
			$this->createMock(BoardService::class),
			$this->createMock(StackService::class),
			$this->cardService,
			$this->permissionService,
			$this->createMock(BoardMapper::class)
		);
	}

	private function createCard(?\DateTimeInterface $done = null): Card {
		$card = new Card();
		$card->setId(1);
		$card->setTitle('Old title');
		$card->setDescription('Old description');
		$card->setStackId(10);
		$card->setType('plain');
		$card->setOwner('user1');
		$card->setOrder(3);
		$card->setDeletedAt(0);
		$card->setArchived(false);
		$card->setDone($done ? \DateTime::createFromInterface($done) : null);
		$card->setStartdate(new \DateTime('2026-01-01T09:00:00+00:00'));
		$card->setColor('ff0000');
		return $card;
	}

	private function todoPayload(string $todo): string {
		return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Nextcloud Deck Test//EN\r\n" . $todo . "\r\nEND:VCALENDAR\r\n";
	}

	public function testBoardPermissionsAreCachedPerBoard(): void {
		$this->permissionService->expects($this->once())
			->method('getPermissions')
			->with(123)
			->willReturn([
				1 => true,
				2 => false,
			]);

		$this->assertTrue($this->backend->checkBoardPermission(123, 1));
		$this->assertFalse($this->backend->checkBoardPermission(123, 2));
		$this->assertFalse($this->backend->checkBoardPermission(123, 3));
	}

	public function testUpdateCardMapsSupportedFields(): void {
		$sourceCard = $this->createCard();
		$currentCard = $this->createCard();
		$updatedCard = $this->createCard();
		$payload = $this->todoPayload(
			"BEGIN:VTODO\r\n"
			. "UID:deck-card-1\r\n"
			. "SUMMARY:New title\r\n"
			. "DESCRIPTION:New description\r\n"
			. "DUE:20260507T100000Z\r\n"
			. "STATUS:COMPLETED\r\n"
			. 'END:VTODO'
		);

		$this->cardService->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($currentCard);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				1,
				'New title',
				10,
				'plain',
				'user1',
				'New description',
				3,
				$this->callback(static fn (?string $value): bool => $value !== null && (new \DateTime($value))->getTimestamp() === 1778148000),
				0,
				false,
				$this->callback(static fn (OptionalNullableValue $value): bool => $value->getValue() instanceof \DateTimeInterface),
				$this->callback(static fn (?string $value): bool => $value !== null && (new \DateTime($value))->getTimestamp() === 1767258000),
				'ff0000'
			)
			->willReturn($updatedCard);

		$this->assertSame($updatedCard, $this->backend->updateCardFromCalendarObject($sourceCard, $payload));
	}

	public function testPercentCompleteMiddleValueKeepsDoneState(): void {
		$done = new \DateTime('2026-01-02T09:00:00+00:00');
		$sourceCard = $this->createCard($done);
		$currentCard = $this->createCard($done);
		$payload = $this->todoPayload(
			"BEGIN:VTODO\r\n"
			. "UID:deck-card-1\r\n"
			. "SUMMARY:Old title\r\n"
			. "PERCENT-COMPLETE:50\r\n"
			. 'END:VTODO'
		);

		$this->cardService->method('find')->willReturn($currentCard);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback(static fn (OptionalNullableValue $value): bool => $value->getValue() instanceof \DateTimeInterface
					&& $value->getValue()->getTimestamp() === $done->getTimestamp()),
				$this->anything(),
				$this->anything()
			)
			->willReturn($currentCard);

		$this->backend->updateCardFromCalendarObject($sourceCard, $payload);
	}

	public function testNeedsActionStatusWinsOverStaleCompletedProperty(): void {
		$done = new \DateTime('2026-01-02T09:00:00+00:00');
		$sourceCard = $this->createCard($done);
		$currentCard = $this->createCard($done);
		$payload = $this->todoPayload(
			"BEGIN:VTODO\r\n"
			. "UID:deck-card-1\r\n"
			. "SUMMARY:Old title\r\n"
			. "STATUS:NEEDS-ACTION\r\n"
			. "COMPLETED:20260102T090000Z\r\n"
			. 'END:VTODO'
		);

		$this->cardService->method('find')->willReturn($currentCard);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback(static fn (OptionalNullableValue $value): bool => $value->getValue() === null),
				$this->anything(),
				$this->anything()
			)
			->willReturn($currentCard);

		$this->backend->updateCardFromCalendarObject($sourceCard, $payload);
	}

	public function testCancelledStatusKeepsDoneState(): void {
		$done = new \DateTime('2026-01-02T09:00:00+00:00');
		$sourceCard = $this->createCard($done);
		$currentCard = $this->createCard($done);
		$payload = $this->todoPayload(
			"BEGIN:VTODO\r\n"
			. "UID:deck-card-1\r\n"
			. "SUMMARY:Old title\r\n"
			. "STATUS:CANCELLED\r\n"
			. "PERCENT-COMPLETE:0\r\n"
			. 'END:VTODO'
		);

		$this->cardService->method('find')->willReturn($currentCard);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback(static fn (OptionalNullableValue $value): bool => $value->getValue() instanceof \DateTimeInterface
					&& $value->getValue()->getTimestamp() === $done->getTimestamp()),
				$this->anything(),
				$this->anything()
			)
			->willReturn($currentCard);

		$this->backend->updateCardFromCalendarObject($sourceCard, $payload);
	}

	public function testEmptyDescriptionClearsDescription(): void {
		$sourceCard = $this->createCard();
		$currentCard = $this->createCard();
		$payload = $this->todoPayload(
			"BEGIN:VTODO\r\n"
			. "UID:deck-card-1\r\n"
			. "SUMMARY:Old title\r\n"
			. "DESCRIPTION:\r\n"
			. 'END:VTODO'
		);

		$this->cardService->method('find')->willReturn($currentCard);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				'',
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything()
			)
			->willReturn($currentCard);

		$this->backend->updateCardFromCalendarObject($sourceCard, $payload);
	}

	public function testMissingDescriptionKeepsCurrentDescription(): void {
		$sourceCard = $this->createCard();
		$currentCard = $this->createCard();
		$payload = $this->todoPayload(
			"BEGIN:VTODO\r\n"
			. "UID:deck-card-1\r\n"
			. "SUMMARY:Old title\r\n"
			. 'END:VTODO'
		);

		$this->cardService->method('find')->willReturn($currentCard);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				'Old description',
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything()
			)
			->willReturn($currentCard);

		$this->backend->updateCardFromCalendarObject($sourceCard, $payload);
	}

	public function testEmptySummaryFallsBackToCurrentTitle(): void {
		$sourceCard = $this->createCard();
		$currentCard = $this->createCard();
		$payload = $this->todoPayload(
			"BEGIN:VTODO\r\n"
			. "UID:deck-card-1\r\n"
			. "SUMMARY:\r\n"
			. "STATUS:NEEDS-ACTION\r\n"
			. 'END:VTODO'
		);

		$this->cardService->method('find')->willReturn($currentCard);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				$this->anything(),
				'Old title',
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback(static fn (OptionalNullableValue $value): bool => $value->getValue() === null),
				$this->anything(),
				$this->anything()
			)
			->willReturn($currentCard);

		$this->backend->updateCardFromCalendarObject($sourceCard, $payload);
	}

	public function testPayloadMustContainExactlyOneTodo(): void {
		$this->expectException(InvalidDataException::class);
		$this->backend->updateCardFromCalendarObject($this->createCard(), "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");
	}

	public function testInvalidCalendarPayloadThrowsInvalidDataException(): void {
		$this->expectException(InvalidDataException::class);
		$this->backend->updateCardFromCalendarObject($this->createCard(), 'not an ics payload');
	}

	public function testDtStartFromPayloadIsIgnored(): void {
		$sourceCard = $this->createCard();
		$currentCard = new Card();
		$currentCard->setId(1);
		$currentCard->setTitle('Old title');
		$currentCard->setDescription('Old description');
		$currentCard->setStackId(10);
		$currentCard->setType('plain');
		$currentCard->setOwner('user1');
		$currentCard->setOrder(3);
		$currentCard->setDeletedAt(0);
		$currentCard->setArchived(false);
		$currentCard->setDone(null);
		$currentCard->setStartdate(null);
		$currentCard->setColor(null);

		$payload = $this->todoPayload(
			"BEGIN:VTODO\r\n"
			. "UID:deck-card-1\r\n"
			. "SUMMARY:Title\r\n"
			. "DTSTART:20260506T100000Z\r\n"
			. 'END:VTODO'
		);

		$this->cardService->method('find')->willReturn($currentCard);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->isNull(),
				$this->anything()
			)
			->willReturn($currentCard);

		$this->backend->updateCardFromCalendarObject($sourceCard, $payload);
	}
}
