<?php

/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Cron;

use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\Service\AttachmentService;
use OCA\Deck\Service\IAttachmentService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class DeleteCronTest extends TestCase {

	/** @var ITimeFactory|MockObject */
	private $timeFactory;
	/** @var BoardMapper|MockObject */
	protected $boardMapper;
	/** @var CardMapper|\PHPUnit\Framework\MockObject\MockObject */
	protected $cardMapper;
	/** @var AttachmentService|\PHPUnit\Framework\MockObject\MockObject */
	private $attachmentService;
	/** @var AttachmentMapper|MockObject */
	private $attachmentMapper;
	/** @var DeleteCron */
	protected $deleteCron;

	public function setUp(): void {
		parent::setUp();
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->attachmentService = $this->createMock(AttachmentService::class);
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->deleteCron = new DeleteCron($this->timeFactory, $this->boardMapper, $this->cardMapper, $this->attachmentService, $this->attachmentMapper);
	}

	protected function getBoard($id) {
		$board = new Board();
		$board->setId($id);
		return $board;
	}

	protected function getCard($id) {
		$card = new Card();
		$card->setId($id);
		return $card;
	}

	public function testDeleteCron() {
		$boards = [
			$this->getBoard(1),
			$this->getBoard(2),
			$this->getBoard(3),
			$this->getBoard(4),
		];
		$this->boardMapper->expects($this->once())
			->method('findToDelete')
			->willReturn($boards);
		$this->boardMapper->expects($this->exactly(count($boards)))
			->method('delete')
			->withConsecutive(
				[$boards[0]],
				[$boards[1]],
				[$boards[2]],
				[$boards[3]]
			);

		$cards = [ $this->getCard(10) ];
		$this->cardMapper->expects($this->once())
			->method('findToDelete')
			->willReturn($cards);
		$this->cardMapper->expects($this->once())
			->method('delete')
			->with($cards[0]);

		$attachment = new Attachment();
		$attachment->setType('deck_file');
		$this->attachmentMapper->expects($this->once())
			->method('findToDelete')
			->willReturn([
				$attachment
			]);
		$service = $this->createMock(IAttachmentService::class);
		$service->expects($this->once())
			->method('delete')
			->with($attachment);
		$this->attachmentService->expects($this->once())
			->method('getService')
			->willReturn($service);
		$this->attachmentMapper->expects($this->once())
			->method('delete')
			->with($attachment);
		$this->invokePrivate($this->deleteCron, 'run', [null]);
	}

	public function testDeleteCronInvalidAttachment() {
		$boards = [];
		$this->boardMapper->expects($this->once())
			->method('findToDelete')
			->willReturn($boards);

		$this->cardMapper->expects($this->once())
			->method('findToDelete')
			->willReturn([]);

		$attachment = new Attachment();
		$attachment->setType('deck_file_invalid');
		$this->attachmentMapper->expects($this->once())
			->method('findToDelete')
			->willReturn([$attachment]);
		$this->attachmentService->expects($this->once())
			->method('getService')
			->will($this->throwException(new InvalidAttachmentType('deck_file_invalid')));
		$this->attachmentMapper->expects($this->once())
			->method('delete')
			->with($attachment);
		$this->invokePrivate($this->deleteCron, 'run', [null]);
	}
}
