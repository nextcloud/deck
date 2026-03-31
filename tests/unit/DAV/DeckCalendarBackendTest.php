<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\DAV;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\LabelService;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Service\StackService;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class DeckCalendarBackendTest extends TestCase {

	private DeckCalendarBackend $backend;
	private CardService $cardService;
	private StackService $stackService;
	private BoardMapper $boardMapper;
	private LabelService $labelService;
	private PermissionService $permissionService;
	private LoggerInterface $logger;

	public function setUp(): void {
		parent::setUp();
		$boardService = $this->createMock(BoardService::class);
		$this->stackService = $this->createMock(StackService::class);
		$this->cardService = $this->createMock(CardService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->labelService = $this->createMock(LabelService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$configService = $this->createMock(ConfigService::class);
		$configService->method('getCalDavListMode')
			->willReturn(ConfigService::SETTING_CALDAV_LIST_MODE_ROOT_TASKS);

		$this->backend = new DeckCalendarBackend(
			$boardService,
			$this->stackService,
			$this->cardService,
			$this->permissionService,
			$this->boardMapper,
			$this->labelService,
			$configService,
			$this->logger
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
				}),
				12
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

	public function testDeleteCardFromCalendarObjectSkipsTrailingDeleteAfterSameBoardStackMove(): void {
		$sourceCard = new Card();
		$sourceCard->setId(321);
		$sourceCard->setStackId(10);

		$currentCard = new Card();
		$currentCard->setId(321);
		$currentCard->setStackId(11);

		$currentStack = new Stack();
		$currentStack->setId(11);
		$currentStack->setBoardId(12);

		$this->cardService->expects($this->once())
			->method('find')
			->with(321)
			->willReturn($currentCard);
		$this->stackService->expects($this->once())
			->method('find')
			->with(11)
			->willReturn($currentStack);
		$this->cardService->expects($this->never())
			->method('delete');

		$this->backend->deleteCalendarObject($sourceCard, 12, 10);
	}

	public function testFindCalendarObjectByNameWithoutDeletedFallbackSkipsSoftDeletedCard(): void {
		$this->cardService->expects($this->once())
			->method('find')
			->with(321)
			->willThrowException(new \Exception('Card not found'));

		$object = $this->backend->findCalendarObjectByName('card-321.ics', 12, null, false);

		$this->assertNull($object);
	}

	public function testFindCalendarObjectByNameResolvesStoredDavUri(): void {
		$card = new Card();
		$card->setId(321);
		$card->setDavUri('client-task.ics');

		$this->cardService->expects($this->once())
			->method('findByDavUriLite')
			->with('client-task.ics', 12, null, true)
			->willReturn($card);

		$object = $this->backend->findCalendarObjectByName('client-task.ics', 12, null, true);

		$this->assertSame($card, $object);
	}

	public function testCreateCalendarObjectUpdatesExistingCardByDavUriWithinBoard(): void {
		$existingCard = new Card();
		$existingCard->setId(222);
		$existingCard->setTitle('Existing');
		$existingCard->setDescription('');
		$existingCard->setStackId(5);
		$existingCard->setType('plain');
		$existingCard->setOrder(999);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);
		$existingCard->setDavUri('client-task.ics');

		$currentStack = new Stack();
		$currentStack->setId(5);
		$currentStack->setBoardId(12);
		$targetStack = new Stack();
		$targetStack->setId(6);
		$targetStack->setBoardId(12);

		$this->cardService->expects($this->once())
			->method('findByDavUriLite')
			->with('client-task.ics', 12, null, true)
			->willReturn($existingCard);
		$this->cardService->expects($this->once())
			->method('find')
			->with(222)
			->willReturn($existingCard);
		$this->cardService->expects($this->never())
			->method('create');
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->willReturnMap([
				[5, $currentStack],
				[6, $targetStack],
			]);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				222,
				'Updated via href',
				6,
				'plain',
				'admin',
				'',
				999,
				null,
				0,
				false,
				$this->callback(static function ($value) {
					return $value instanceof OptionalNullableValue && $value->getValue() === null;
				}),
				12
			)
			->willReturn($existingCard);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:different-uid
SUMMARY:Updated via href
STATUS:NEEDS-ACTION
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->createCalendarObject(12, 'admin', $calendarData, null, 6, 'client-task.ics');
	}

	public function testCreateCalendarObjectMovesExistingCardToPreferredStackInSameBoard(): void {
		$configService = $this->createMock(ConfigService::class);
		$configService->method('getCalDavListMode')
			->willReturn(ConfigService::SETTING_CALDAV_LIST_MODE_PER_LIST_CALENDAR);
		$boardService = $this->createMock(BoardService::class);
		$permissionService = $this->createMock(PermissionService::class);
		$backend = new DeckCalendarBackend(
			$boardService,
			$this->stackService,
			$this->cardService,
			$permissionService,
			$this->boardMapper,
			$this->labelService,
			$configService,
			$this->logger
		);

		$existingCard = new Card();
		$existingCard->setId(91);
		$existingCard->setTitle('Test neue URI');
		$existingCard->setDescription('');
		$existingCard->setStackId(5);
		$existingCard->setType('plain');
		$existingCard->setOrder(999);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);

		$currentStack = new Stack();
		$currentStack->setId(5);
		$currentStack->setBoardId(12);
		$targetStack = new Stack();
		$targetStack->setId(6);
		$targetStack->setBoardId(12);

		$this->cardService->expects($this->exactly(2))
			->method('find')
			->withConsecutive(
				[91, true, false],
				[91]
			)
			->willReturnOnConsecutiveCalls($existingCard, $existingCard);
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->willReturnMap([
				[5, $currentStack],
				[6, $targetStack],
			]);
		$this->cardService->expects($this->once())
			->method('update')
			->with(
				91,
				'Test neue URI',
				6,
				'plain',
				'admin',
				'',
				999,
				null,
				0,
				false,
				$this->callback(static function ($value) {
					return $value instanceof OptionalNullableValue && $value->getValue() === null;
				}),
				12
			)
			->willReturn($existingCard);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-91
SUMMARY:Test neue URI
RELATED-TO:deck-stack-5
STATUS:NEEDS-ACTION
END:VTODO
END:VCALENDAR
ICS;

		$backend->createCalendarObject(12, 'admin', $calendarData, 91, 6, 'deck-card-91.ics');
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
				}),
				12
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
				$this->callback(fn ($value) => $value instanceof \DateTime && $value->format('c') === '2026-03-03T12:00:00+00:00'),
				null
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

	public function testCreateCardFromCalendarStoresCustomDavUri(): void {
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
				'',
				null,
				'client-task.ics'
			)
			->willReturn($card);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
SUMMARY:Created task
RELATED-TO:deck-stack-88
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->createCalendarObject(12, 'admin', $calendarData, null, null, 'client-task.ics');
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
				null,
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
				null,
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

		$this->cardService->expects($this->exactly(2))
			->method('find')
			->withConsecutive(
				[123, true, false],
				[123]
			)
			->willReturnOnConsecutiveCalls($sourceCard, $sourceCard);
		$this->stackService->expects($this->exactly(3))
			->method('find')
			->willReturnMap([
				[42, $sourceStack],
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
				$this->isInstanceOf(OptionalNullableValue::class),
				12
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

	public function testUpdateCardWithInProgressStatusClearsDone(): void {
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
		$existingCard->setDone(new \DateTime('2026-03-01T10:00:00+00:00'));

		$this->cardService->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($existingCard);
		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$this->stackService->expects($this->exactly(2))
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
					return $value->getValue() === null;
				}),
				12
			)
			->willReturn($existingCard);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Card
DESCRIPTION:Description
STATUS:IN-PROCESS
PERCENT-COMPLETE:50
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testUpdateCardSyncsAppleTagsToLabels(): void {
		$sourceCard = new Card();
		$sourceCard->setId(123);

		$existingCard = new Card();
		$existingCard->setId(123);
		$existingCard->setTitle('Card');
		$existingCard->setDescription('Old description');
		$existingCard->setStackId(42);
		$existingCard->setType('plain');
		$existingCard->setOrder(0);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);
		$existingCard->setLabels([]);

		$updatedCard = new Card();
		$updatedCard->setId(123);

		$this->cardService->expects($this->exactly(2))
			->method('find')
			->with(123)
			->willReturnOnConsecutiveCalls($existingCard, $existingCard);

		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->with(42)
			->willReturn($currentStack);

		$this->cardService->expects($this->once())
			->method('update')
			->willReturn($updatedCard);

		$label = new Label();
		$label->setId(7);
		$label->setTitle('Test');
		$board = new Board();
		$board->setId(12);
		$board->setLabels([$label]);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(12, true, false)
			->willReturn($board);

		$this->cardService->expects($this->once())
			->method('assignLabel')
			->with(123, 7);
		$this->cardService->expects($this->never())
			->method('removeLabel');
		$this->labelService->expects($this->never())
			->method('create');
		$this->permissionService->expects($this->once())
			->method('getPermissions')
			->with(12)
			->willReturn([
				\OCA\Deck\Db\Acl::PERMISSION_MANAGE => true,
			]);

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Card
DESCRIPTION:Updated description
X-APPLE-TAGS:Test
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testUpdateCardSyncsCategoriesEvenWhenOtherFieldsAreUnchanged(): void {
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
		$existingCard->setLabels([]);

		$this->cardService->expects($this->exactly(2))
			->method('find')
			->with(123)
			->willReturnOnConsecutiveCalls($existingCard, $existingCard);

		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$this->stackService->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($currentStack);

		$label = new Label();
		$label->setId(7);
		$label->setTitle('Test');
		$board = new Board();
		$board->setId(12);
		$board->setLabels([$label]);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(12, true, false)
			->willReturn($board);

		$this->permissionService->expects($this->once())
			->method('getPermissions')
			->with(12)
			->willReturn([
				\OCA\Deck\Db\Acl::PERMISSION_MANAGE => true,
			]);

		$this->cardService->expects($this->never())
			->method('update');
		$this->labelService->expects($this->never())
			->method('create');
		$this->cardService->expects($this->once())
			->method('assignLabel')
			->with(123, 7);
		$this->cardService->expects($this->never())
			->method('removeLabel');

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Card
DESCRIPTION:Description
X-APPLE-TAGS:Test
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testUpdateCardDoesNotAutoCreateLabelsWithoutManagePermission(): void {
		$sourceCard = new Card();
		$sourceCard->setId(123);

		$existingCard = new Card();
		$existingCard->setId(123);
		$existingCard->setTitle('Card');
		$existingCard->setDescription('Old description');
		$existingCard->setStackId(42);
		$existingCard->setType('plain');
		$existingCard->setOrder(0);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);
		$existingCard->setLabels([]);

		$updatedCard = new Card();
		$updatedCard->setId(123);

		$this->cardService->expects($this->exactly(2))
			->method('find')
			->with(123)
			->willReturnOnConsecutiveCalls($existingCard, $existingCard);

		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->with(42)
			->willReturn($currentStack);

		$this->cardService->expects($this->once())
			->method('update')
			->willReturn($updatedCard);

		$board = new Board();
		$board->setId(12);
		$board->setLabels([]);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(12, true, false)
			->willReturn($board);

		$this->permissionService->expects($this->once())
			->method('getPermissions')
			->with(12)
			->willReturn([
				\OCA\Deck\Db\Acl::PERMISSION_MANAGE => false,
			]);

		$this->labelService->expects($this->never())
			->method('create');
		$this->cardService->expects($this->never())
			->method('assignLabel');
		$this->cardService->expects($this->never())
			->method('removeLabel');

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Card
DESCRIPTION:Updated description
CATEGORIES:Alpha,Beta
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testUpdateCardKeepsExistingLabelsWhenCategoriesCannotBeCreated(): void {
		$sourceCard = new Card();
		$sourceCard->setId(123);

		$currentLabel = new Label();
		$currentLabel->setId(99);
		$currentLabel->setTitle('Existing');

		$existingCard = new Card();
		$existingCard->setId(123);
		$existingCard->setTitle('Card');
		$existingCard->setDescription('Old description');
		$existingCard->setStackId(42);
		$existingCard->setType('plain');
		$existingCard->setOrder(0);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);
		$existingCard->setLabels([$currentLabel]);

		$updatedCard = new Card();
		$updatedCard->setId(123);

		$this->cardService->expects($this->exactly(2))
			->method('find')
			->with(123)
			->willReturnOnConsecutiveCalls($existingCard, $existingCard);

		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->with(42)
			->willReturn($currentStack);

		$this->cardService->expects($this->once())
			->method('update')
			->willReturn($updatedCard);

		$board = new Board();
		$board->setId(12);
		$board->setLabels([]);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(12, true, false)
			->willReturn($board);

		$this->permissionService->expects($this->once())
			->method('getPermissions')
			->with(12)
			->willReturn([
				\OCA\Deck\Db\Acl::PERMISSION_MANAGE => false,
			]);

		$this->labelService->expects($this->never())
			->method('create');
		$this->cardService->expects($this->never())
			->method('assignLabel');
		$this->cardService->expects($this->never())
			->method('removeLabel');

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Card
DESCRIPTION:Updated description
CATEGORIES:Alpha
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testUpdateCardLimitsAutoCreatedLabelsPerSync(): void {
		$sourceCard = new Card();
		$sourceCard->setId(123);

		$existingCard = new Card();
		$existingCard->setId(123);
		$existingCard->setTitle('Card');
		$existingCard->setDescription('Old description');
		$existingCard->setStackId(42);
		$existingCard->setType('plain');
		$existingCard->setOrder(0);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);
		$existingCard->setLabels([]);

		$updatedCard = new Card();
		$updatedCard->setId(123);

		$this->cardService->expects($this->exactly(2))
			->method('find')
			->with(123)
			->willReturnOnConsecutiveCalls($existingCard, $existingCard);

		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->with(42)
			->willReturn($currentStack);

		$this->cardService->expects($this->once())
			->method('update')
			->willReturn($updatedCard);

		$board = new Board();
		$board->setId(12);
		$board->setLabels([]);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(12, true, false)
			->willReturn($board);

		$this->permissionService->expects($this->once())
			->method('getPermissions')
			->with(12)
			->willReturn([
				\OCA\Deck\Db\Acl::PERMISSION_MANAGE => true,
			]);

		$labels = [];
		for ($i = 1; $i <= 5; $i++) {
			$label = new Label();
			$label->setId($i);
			$label->setTitle('Tag ' . $i);
			$labels[] = $label;
		}

		$this->labelService->expects($this->exactly(5))
			->method('create')
			->withConsecutive(
				['Tag 1', '31CC7C', 12],
				['Tag 2', '31CC7C', 12],
				['Tag 3', '31CC7C', 12],
				['Tag 4', '31CC7C', 12],
				['Tag 5', '31CC7C', 12],
			)
			->willReturnOnConsecutiveCalls(...$labels);

		$this->cardService->expects($this->exactly(5))
			->method('assignLabel')
			->withConsecutive(
				[123, 1],
				[123, 2],
				[123, 3],
				[123, 4],
				[123, 5],
			);
		$this->cardService->expects($this->never())
			->method('removeLabel');

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Card
DESCRIPTION:Updated description
CATEGORIES:Tag 1,Tag 2,Tag 3,Tag 4,Tag 5,Tag 6
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testUpdateCardKeepsExistingLabelsWhenAutoCreateLimitIsExceeded(): void {
		$sourceCard = new Card();
		$sourceCard->setId(123);

		$currentLabel = new Label();
		$currentLabel->setId(99);
		$currentLabel->setTitle('Existing');

		$existingCard = new Card();
		$existingCard->setId(123);
		$existingCard->setTitle('Card');
		$existingCard->setDescription('Old description');
		$existingCard->setStackId(42);
		$existingCard->setType('plain');
		$existingCard->setOrder(0);
		$existingCard->setOwner('admin');
		$existingCard->setDeletedAt(0);
		$existingCard->setArchived(false);
		$existingCard->setDone(null);
		$existingCard->setLabels([$currentLabel]);

		$updatedCard = new Card();
		$updatedCard->setId(123);

		$this->cardService->expects($this->exactly(2))
			->method('find')
			->with(123)
			->willReturnOnConsecutiveCalls($existingCard, $existingCard);

		$currentStack = new Stack();
		$currentStack->setId(42);
		$currentStack->setBoardId(12);
		$this->stackService->expects($this->exactly(2))
			->method('find')
			->with(42)
			->willReturn($currentStack);

		$this->cardService->expects($this->once())
			->method('update')
			->willReturn($updatedCard);

		$board = new Board();
		$board->setId(12);
		$board->setLabels([]);

		$this->boardMapper->expects($this->once())
			->method('find')
			->with(12, true, false)
			->willReturn($board);

		$this->permissionService->expects($this->once())
			->method('getPermissions')
			->with(12)
			->willReturn([
				\OCA\Deck\Db\Acl::PERMISSION_MANAGE => true,
			]);

		$labels = [];
		for ($i = 1; $i <= 5; $i++) {
			$label = new Label();
			$label->setId($i);
			$label->setTitle('Tag ' . $i);
			$labels[] = $label;
		}

		$this->labelService->expects($this->exactly(5))
			->method('create')
			->withConsecutive(
				['Tag 1', '31CC7C', 12],
				['Tag 2', '31CC7C', 12],
				['Tag 3', '31CC7C', 12],
				['Tag 4', '31CC7C', 12],
				['Tag 5', '31CC7C', 12],
			)
			->willReturnOnConsecutiveCalls(...$labels);

		$this->cardService->expects($this->exactly(5))
			->method('assignLabel')
			->withConsecutive(
				[123, 1],
				[123, 2],
				[123, 3],
				[123, 4],
				[123, 5],
			);
		$this->cardService->expects($this->never())
			->method('removeLabel');

		$calendarData = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:deck-card-123
SUMMARY:Card
DESCRIPTION:Updated description
CATEGORIES:Tag 1,Tag 2,Tag 3,Tag 4,Tag 5,Tag 6
END:VTODO
END:VCALENDAR
ICS;

		$this->backend->updateCalendarObject($sourceCard, $calendarData);
	}

	public function testGetObjectRevisionFingerprintUsesKnownBoardContextWithoutStackLookup(): void {
		$card = new Card();
		$card->setId(123);
		$card->setStackId(42);

		$this->stackService->expects($this->never())
			->method('find');

		$fingerprint = $this->backend->getObjectRevisionFingerprint($card, 12, 42);

		$this->assertSame(ConfigService::SETTING_CALDAV_LIST_MODE_ROOT_TASKS . '|stack:42', $fingerprint);
	}
}
