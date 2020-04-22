<?php
/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Card;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\BoardMapper;
use OCP\IConfig;
use OCP\IL10N;
use \Test\TestCase;

class DefaultBoardServiceTest extends TestCase {

	/** @var DefaultBoardService */
	private $service;

	/** @var BoardService */
	private $boardService;

	/** @var StackService */
	private $stackService;

	/** @var CardService */
	private $cardService;

	/** @var BoardMapper */
	private $boardMapper;

	/** @var IConfig */
	private $config;

	private $l10n;

	private $userId = 'admin';

	public function setUp(): void {
		parent::setUp();
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->stackService = $this->createMock(StackService::class);
		$this->cardService = $this->createMock(CardService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userId = 'admin';

		$this->service = new DefaultBoardService(
			$this->l10n,
			$this->boardMapper,
			$this->boardService,
			$this->stackService,
			$this->cardService,
			$this->config
		);
	}

	public function testCheckFirstRunCaseTrue() {
		$appName = 'deck';
		$userBoards = [];

		$this->config->expects($this->once())
			->method('getUserValue')
			->willReturn('yes');

		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->willReturn($userBoards);

		$this->config->expects($this->once())
			->method('setUserValue');

		$result = $this->service->checkFirstRun($this->userId);
		$this->assertEquals($result, true);
	}

	public function testCheckFirstRunCaseFalse() {
		$appName = 'deck';
		$board = new Board();
		$board->setTitle('Personal');
		$board->setOwner($this->userId);
		$board->setColor('317CCC');

		$userBoards = [$board];

		$this->config->expects($this->once())
			->method('getUserValue')
			->willReturn('no');

		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->willReturn($userBoards);

		$result = $this->service->checkFirstRun($this->userId);
		$this->assertEquals($result, false);
	}

	public function testCreateDefaultBoard() {
		$title = 'Personal';
		$color = '317CCC';
		$boardId = 5;

		$board = new Board();
		$board->setId($boardId);
		$board->setTitle($title);
		$board->setOwner($this->userId);
		$board->setColor($color);
		$this->boardService->expects($this->once())
			->method('create')
			->willReturn($board);

		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text) {
				return $text;
			});

		$stackToDoId = '123';
		$stackToDo = $this->assembleTestStack('To do', $stackToDoId, $boardId);

		$stackDoingId = '124';
		$stackDoing = $this->assembleTestStack('Doing', $stackDoingId, $boardId);

		$stackDoneId = '125';
		$stackDone = $this->assembleTestStack('Done', $stackDoneId, $boardId);
		$this->stackService->expects($this->exactly(3))
			->method('create')
			->withConsecutive(
				[$this->l10n->t('To do'), $boardId, 1],
				[$this->l10n->t('Doing'), $boardId, 1],
				[$this->l10n->t('Done'),  $boardId, 1]
			)
			->willReturnOnConsecutiveCalls($stackToDo, $stackDoing, $stackDone);

		$cardExampleTask3 = $this->assembleTestCard('Example Task 3', $stackToDoId, $this->userId);
		$cardExampleTask2 = $this->assembleTestCard('Example Task 2', $stackDoingId, $this->userId);
		$cardExampleTask1 = $this->assembleTestCard('Example Task 1', $stackDoneId, $this->userId);
		$this->cardService->expects($this->exactly(3))
			->method('create')
			->withConsecutive(
				['Example Task 3', $stackToDoId, 'text', 0, $this->userId],
				['Example Task 2', $stackDoingId, 'text', 0, $this->userId],
				['Example Task 1', $stackDoneId, 'text', 0, $this->userId]
			)
			->willReturnonConsecutiveCalls($cardExampleTask3, $cardExampleTask2, $cardExampleTask1);

		$result = $this->service->createDefaultBoard($title, $this->userId, $color);

		$this->assertEquals($result->getTitle(), $title);
		$this->assertEquals($result->getOwner(), $this->userId);
		$this->assertEquals($result->getColor(), $color);
	}

	private function assembleTestStack($title, $id, $boardId) {
		$stack = new Stack();
		$stack->setId($id);
		$stack->setTitle($title);
		$stack->setBoardId($boardId);
		$stack->setOrder(1);

		return $stack;
	}

	private function assembleTestCard($title, $stackId, $userId) {
		$card = new Card();
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType('text');
		$card->setOrder(0);
		$card->setOwner($userId);

		return $card;
	}
}
