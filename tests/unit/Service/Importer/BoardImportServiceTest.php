<?php

/**
 * @copyright Copyright (c) 2021 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service\Importer;

use OC\Comments\Comment;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\BoardImportGetAllowedEvent;
use OCA\Deck\Service\AttachmentService;
use OCA\Deck\Service\Importer\Systems\TrelloJsonService;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class BoardImportServiceTest extends \Test\TestCase {
	/** @var IDBConnection|MockObject */
	protected $dbConn;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var AclMapper|MockObject */
	private $aclMapper;
	/** @var LabelMapper|MockObject */
	private $labelMapper;
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var AssignmentMapper|MockObject */
	private $assignmentMapper;
	/** @var AttachmentMapper|MockObject */
	private $attachmentMapper;
	/** @var ICommentsManager|MockObject */
	private $commentsManager;
	/** @var IEventDispatcher|MockObject */
	private $eventDispatcher;
	/** @var TrelloJsonService|MockObject */
	private $trelloJsonService;
	/** @var AttachmentService|MockObject */
	private $attachmentService;
	/** @var BoardImportService|MockObject */
	private $boardImportService;
	public function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->attachmentService = $this->createMock(AttachmentService::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->boardImportService = new BoardImportService(
			$this->userManager,
			$this->boardMapper,
			$this->aclMapper,
			$this->labelMapper,
			$this->stackMapper,
			$this->assignmentMapper,
			$this->attachmentMapper,
			$this->cardMapper,
			$this->commentsManager,
			$this->eventDispatcher,
			$this->createMock(LoggerInterface::class),
			$this->attachmentService,
		);

		$this->boardImportService->setSystem('trelloJson');

		$this->eventDispatcher
			->method('dispatchTyped')
			->willReturnCallback(function (BoardImportGetAllowedEvent $event) {
				$event->getService()->addAllowedImportSystem([
					'name' => TrelloJsonService::$name,
					'class' => TrelloJsonService::class,
					'internalName' => 'trelloJson'
				]);
			});

		$data = json_decode(file_get_contents(__DIR__ . '/../../../data/data-trelloJson.json'));
		$this->boardImportService->setData($data);

		$configFile = __DIR__ . '/../../../data/config-trelloJson.json';
		$configInstance = json_decode(file_get_contents($configFile));
		$this->boardImportService->setConfigInstance($configInstance);

		$this->trelloJsonService = $this->createMock(TrelloJsonService::class);
		$this->trelloJsonService
			->method('getJsonSchemaPath')
			->willReturn($configFile);
		$this->trelloJsonService
			->method('getBoards')
			->willReturn([$data]);
		$this->boardImportService->setImportSystem($this->trelloJsonService);

		$owner = $this->createMock(IUser::class);
		$owner
			->method('getUID')
			->willReturn('admin');

		$johndoe = $this->createMock(IUser::class);
		$johndoe
			->method('getUID')
			->willReturn('johndoe');
		$this->userManager
			->method('get')
			->withConsecutive(
				['admin'],
				['johndoe']
			)
			->willReturnonConsecutiveCalls(
				$owner,
				$johndoe
			);
	}

	public function testImportSuccess() {
		$this->userManager->method('userExists')
			->willReturn(true);

		$board = new Board();
		$board->setOwner('admin');
		$this->trelloJsonService
			->method('getBoard')
			->willReturn($board);

		$this->boardMapper
			->expects($this->once())
			->method('insert');

		$this->trelloJsonService
			->method('getAclList')
			->willReturn([new Acl()]);
		$this->aclMapper
			->expects($this->once())
			->method('insert');

		$this->trelloJsonService
			->method('getLabels')
			->willReturn([new Label()]);
		$this->labelMapper
			->expects($this->once())
			->method('insert');

		$this->trelloJsonService
			->method('getStacks')
			->willReturn([new Stack()]);
		$this->stackMapper
			->expects($this->once())
			->method('insert');

		$this->trelloJsonService
			->method('getCards')
			->willReturn([new Card()]);
		$this->cardMapper
			->expects($this->any())
			->method('insert');

		$this->trelloJsonService
			->method('getComments')
			->willReturn([
				'fakecardid' => [new Comment()]
			]);
		$this->commentsManager
			->expects($this->once())
			->method('save');

		$assignment = new Assignment();
		$assignment->setId(1);
		$this->trelloJsonService
			->method('getCardAssignments')
			->willReturn([
				'fakecardid' => [$assignment]
			]);
		$this->assignmentMapper
			->expects($this->once())
			->method('insert')
			->willReturn($assignment);

		$this->boardImportService->import();
		self::assertTrue(true);
	}

	/**
	 * Stub every step of the import so that a test only has to state which of
	 * them it expects to be skipped.
	 */
	private function stubFullImport(): void {
		$this->userManager->method('userExists')->willReturn(true);

		$board = new Board();
		$board->setOwner('admin');
		$this->trelloJsonService->method('getBoard')->willReturn($board);
		$this->trelloJsonService->method('getAclList')->willReturn([new Acl()]);
		$this->trelloJsonService->method('getLabels')->willReturn([new Label()]);
		$this->trelloJsonService->method('getStacks')->willReturn([new Stack()]);
		$this->trelloJsonService->method('getCards')->willReturn([new Card()]);
		$this->trelloJsonService->method('getCardLabelAssignment')->willReturn([1 => [2]]);
		$this->trelloJsonService->method('getComments')->willReturn(['fakecardid' => [new Comment()]]);

		$assignment = new Assignment();
		$assignment->setId(1);
		$this->trelloJsonService->method('getCardAssignments')->willReturn(['fakecardid' => [$assignment]]);
		$this->assignmentMapper->method('insert')->willReturn($assignment);
	}

	public function testEverythingIsImportedByDefault() {
		self::assertEquals(new ImportOptions(), $this->boardImportService->getOptions());
	}

	public function testSetOptionsIsFluent() {
		$options = new ImportOptions(importCards: false);

		self::assertSame($this->boardImportService, $this->boardImportService->setOptions($options));
		self::assertSame($options, $this->boardImportService->getOptions());
	}

	public function testImportWithoutCardsStillImportsTheBoardStructure() {
		$this->stubFullImport();
		$this->boardImportService->setOptions(new ImportOptions(importCards: false));

		$this->boardMapper->expects($this->once())->method('insert');
		$this->aclMapper->expects($this->once())->method('insert');
		$this->labelMapper->expects($this->once())->method('insert');
		$this->stackMapper->expects($this->once())->method('insert');
		// nothing below the card level may be touched without cards
		$this->cardMapper->expects($this->never())->method('insert');
		$this->cardMapper->expects($this->never())->method('assignLabel');
		$this->trelloJsonService->expects($this->never())->method('importAttachments');
		$this->commentsManager->expects($this->never())->method('save');
		$this->assignmentMapper->expects($this->never())->method('insert');

		$this->boardImportService->import();
	}

	public function testImportWithoutAttachments() {
		$this->stubFullImport();
		$this->boardImportService->setOptions(new ImportOptions(importAttachments: false));

		$this->trelloJsonService->expects($this->never())->method('importAttachments');
		$this->cardMapper->expects($this->atLeastOnce())->method('insert');
		$this->commentsManager->expects($this->once())->method('save');

		$this->boardImportService->import();
	}

	public function testImportWithoutComments() {
		$this->stubFullImport();
		$this->boardImportService->setOptions(new ImportOptions(importComments: false));

		$this->commentsManager->expects($this->never())->method('save');
		$this->trelloJsonService->expects($this->never())->method('getComments');
		$this->cardMapper->expects($this->atLeastOnce())->method('insert');

		$this->boardImportService->import();
	}

	public function testImportWithoutAssignments() {
		$this->stubFullImport();
		$this->boardImportService->setOptions(new ImportOptions(importAssignments: false));

		$this->assignmentMapper->expects($this->never())->method('insert');
		$this->trelloJsonService->expects($this->never())->method('getCardAssignments');
		$this->cardMapper->expects($this->atLeastOnce())->method('insert');

		$this->boardImportService->import();
	}

	public function testImportWithoutLabelsAlsoSkipsTheirCardAssignment() {
		$this->stubFullImport();
		$this->boardImportService->setOptions(new ImportOptions(importLabels: false));

		$this->labelMapper->expects($this->never())->method('insert');
		$this->cardMapper->expects($this->never())->method('assignLabel');
		$this->trelloJsonService->expects($this->never())->method('getCardLabelAssignment');
		$this->cardMapper->expects($this->atLeastOnce())->method('insert');

		$this->boardImportService->import();
	}

	public function testImportWithoutSharing() {
		$this->stubFullImport();
		$this->boardImportService->setOptions(new ImportOptions(importSharing: false));

		$this->aclMapper->expects($this->never())->method('insert');
		$this->trelloJsonService->expects($this->never())->method('getAclList');
		$this->boardMapper->expects($this->once())->method('insert');

		$this->boardImportService->import();
	}

	public function testImportOfNothingButTheBoardItself() {
		$this->stubFullImport();
		$this->boardImportService->setOptions(new ImportOptions(
			importCards: false,
			importLabels: false,
			importSharing: false,
		));

		$this->boardMapper->expects($this->once())->method('insert');
		$this->stackMapper->expects($this->once())->method('insert');
		$this->aclMapper->expects($this->never())->method('insert');
		$this->labelMapper->expects($this->never())->method('insert');
		$this->cardMapper->expects($this->never())->method('insert');

		$this->boardImportService->import();
	}

	public function testImportWrapsFailuresIntoABadRequest() {
		$this->stubFullImport();
		$this->boardMapper->method('insert')->willThrowException(new \RuntimeException('database is gone'));

		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage('database is gone');

		$this->boardImportService->import();
	}
}
