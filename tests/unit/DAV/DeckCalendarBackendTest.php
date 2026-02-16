<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\DAV;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\StackService;
use Test\TestCase;

class DeckCalendarBackendTest extends TestCase {

	private DeckCalendarBackend $backend;
	private CardService $cardService;
	private StackService $stackService;

	public function setUp(): void {
		parent::setUp();
		$boardService = $this->createMock(BoardService::class);
		$this->stackService = $this->createMock(StackService::class);
		$this->cardService = $this->createMock(CardService::class);
		$permissionService = $this->createMock(PermissionService::class);
		$boardMapper = $this->createMock(BoardMapper::class);

		$this->backend = new DeckCalendarBackend(
			$boardService,
			$this->stackService,
			$this->cardService,
			$permissionService,
			$boardMapper
		);
	}

	public function testUpdateCardFromCalendarData(): void {
		$sourceCard = new Card();
		$sourceCard->setId(123);

		$existingCard = new Card();
		$existingCard->setId(123);
		$existingCard->setTitle('Old title');
		$existingCard->setDescription('Old description');
		$existingCard->setStackId(42);
		$existingCard->setType('plain');
		$existingCard->setOrder(5);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);

		$this->cardService->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($existingCard);
		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$targetStack = new Stack();
		$targetStack->setId(88);
		$targetStack->setBoardId(12);
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->willReturnMap([
				[42, $currentStack],
				[88, $targetStack],
			]);

		$this->cardService->expects($this->once())
			->method('update')
			->with(
				123,
				'Updated card',
				88,
				'plain',
				'admin',
				'Updated description',
				5,
				'2026-03-02T08:00:00+00:00',
				0,
				false,
				$this->callback(function ($value) {
					if (!($value instanceof OptionalNullableValue)) {
						return false;
					}
					$done = $value->getValue();
					return $done instanceof \DateTime && $done->format('c') === '2026-03-01T10:00:00+00:00';
				})
			)
			->willReturn($existingCard);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Updated card
DESCRIPTION:Updated description
RELATED-TO:deck-stack-88
DUE:20260302T080000Z
STATUS:COMPLETED
COMPLETED:20260301T100000Z
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testUpdateStackFromCalendarData(): void {
		$sourceStack = new Stack();
		$sourceStack->setId(77);

		$stack = new Stack();
		$stack->setId(77);
		$stack->setTitle('Old list');
		$stack->setBoardId(12);
		$stack->setOrder(3);
		$stack->setDeletedAt(0);

		$this->stackService->expects($this->once())
			->method('find')
			->with(77)
			->willReturn($stack);

		$this->stackService->expects($this->once())
			->method('update')
			->with(77, 'Updated list', 12, 3, 0)
			->willReturn($stack);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-stack-77
SUMMARY:List : Updated list
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceStack, $calendarData);
	}

	public function testDeleteCardFromCalendarObject(): void {
		$sourceCard = new Card();
		$sourceCard->setId(321);

		$this->cardService->expects($this->once())
			->method('delete')
			->with(321);
		$this->stackService->expects($this->never())
			->method('delete');

		$this->backend->deleteCalendarObject($sourceCard);
	}

	public function testDeleteStackFromCalendarObject(): void {
		$sourceStack = new Stack();
		$sourceStack->setId(654);

		$this->stackService->expects($this->once())
			->method('delete')
			->with(654);
		$this->cardService->expects($this->never())
			->method('delete');

		$this->backend->deleteCalendarObject($sourceStack);
	}

	public function testUpdateCardWithCompletedWithoutStatusMarksDone(): void {
		$sourceCard = new Card();
		$sourceCard->setId(123);

		$existingCard = new Card();
		$existingCard->setId(123);
		$existingCard->setTitle('Card');
		$existingCard->setDescription('Description');
		$existingCard->setStackId(42);
		$existingCard->setType('plain');
		$existingCard->setOrder(0);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);

		$this->cardService->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($existingCard);
		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$this->stackService->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($currentStack);

		$this->cardService->expects($this->once())
			->method('update')
			->with(
				123,
				'Card',
				42,
				'plain',
				'admin',
				'Description',
				0,
				null,
				0,
				false,
				$this->callback(function ($value) {
					if (!($value instanceof OptionalNullableValue)) {
						return false;
					}
					$done = $value->getValue();
					return $done instanceof \DateTime && $done->format('c') === '2026-03-01T10:00:00+00:00';
				})
			)
			->willReturn($existingCard);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Card
DESCRIPTION:Description
COMPLETED:20260301T100000Z
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testCreateCardFromCalendarUsesRelatedStack(): void {
		$stack = new Stack();
		$stack->setId(88);
		$stack->setBoardId(12);

		$card = new Card();
		$this->stackService->expects($this->once())
			->method('find')
			->with(88)
			->willReturn($stack);
		$this->stackService->expects($this->never())
			->method('findAll');
		$this->cardService->expects($this->once())
			->method('create')
			->with(
				'Created task',
				88,
				'plain',
				999,
				'admin',
				'From mac',
				$this->callback(fn ($value) => $value instanceof \DateTime && $value->format('c') === '2026-03-03T12:00:00+00:00')
			)
			->willReturn($card);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
SUMMARY:Created task
DESCRIPTION:From mac
RELATED-TO:deck-stack-88
DUE:20260303T120000Z
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->createCalendarObject(12, 'admin', $calendarData);
	}

	public function testCreateCardFromCalendarFallsBackToDefaultStack(): void {
		$stackA = new Stack();
		$stackA->setId(5);
		$stackA->setOrder(3);
		$stackB = new Stack();
		$stackB->setId(7);
		$stackB->setOrder(0);

		$card = new Card();
		$this->stackService->expects($this->once())
			->method('findAll')
			->with(12)
			->willReturn([$stackA, $stackB]);
		$this->cardService->expects($this->once())
			->method('create')
			->with(
				'Created without relation',
				7,
				'plain',
				999,
				'admin',
				'',
				null
			)
			->willReturn($card);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
SUMMARY:Created without relation
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->createCalendarObject(12, 'admin', $calendarData);
	}

	public function testCreateCardFromCalendarWithForeignRelatedStackFallsBackToDefaultStack(): void {
		$foreignStack = new Stack();
		$foreignStack->setId(99);
		$foreignStack->setBoardId(999);

		$stackA = new Stack();
		$stackA->setId(5);
		$stackA->setOrder(3);
		$stackB = new Stack();
		$stackB->setId(7);
		$stackB->setOrder(0);

		$card = new Card();
		$this->stackService->expects($this->once())
			->method('find')
			->with(99)
			->willReturn($foreignStack);
		$this->stackService->expects($this->once())
			->method('findAll')
			->with(12)
			->willReturn([$stackA, $stackB]);
		$this->cardService->expects($this->once())
			->method('create')
			->with(
				'Foreign related stack',
				7,
				'plain',
				999,
				'admin',
				'',
				null
			)
			->willReturn($card);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
SUMMARY:Foreign related stack
RELATED-TO:deck-stack-99
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->createCalendarObject(12, 'admin', $calendarData);
	}

	public function testCreateCardFromCalendarWithExistingDeckUidUpdatesInsteadOfCreating(): void {
		$sourceCard = new Card();
		$sourceCard->setId(123);
		$sourceCard->setTitle('Old title');
		$sourceCard->setDescription('Old description');
		$sourceCard->setStackId(42);
		$sourceCard->setType('plain');
		$sourceCard->setOrder(2);
		$sourceCard->setOwner('admin');
		$sourceCard->setDeletedAt(0);
		$sourceCard->setArchived(false);
		$sourceCard->setDone(null);

		$sourceStack = new Stack();
		$sourceStack->setId(42);
		$sourceStack->setBoardId(12);
		$targetStack = new Stack();
		$targetStack->setId(88);
		$targetStack->setBoardId(12);

		$this->cardService->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($sourceCard);
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->willReturnMap([
				[42, $sourceStack],
				[88, $targetStack],
			]);
		$this->cardService->expects($this->never())
			->method('create');
		$this->stackService->expects($this->never())
			->method('findAll');
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				123,
				'Updated by uid',
				88,
				'plain',
				'admin',
				'Moved back',
				2,
				null,
				0,
				false,
				$this->isInstanceOf(OptionalNullableValue::class)
			)
			->willReturn($sourceCard);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Updated by uid
DESCRIPTION:Moved back
RELATED-TO:deck-stack-88
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->createCalendarObject(12, 'admin', $calendarData);
	}
}
