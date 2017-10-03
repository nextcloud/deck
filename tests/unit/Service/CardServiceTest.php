<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service;


use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\StatusException;
use Test\TestCase;

class CardServiceTest extends TestCase {

    /** @var CardService|\PHPUnit_Framework_MockObject_MockObject */
    private $cardService;
    /** @var CardMapper|\PHPUnit_Framework_MockObject_MockObject */
    private $cardMapper;
    /** @var StackMapper|\PHPUnit_Framework_MockObject_MockObject */
    private $stackMapper;
    /** @var PermissionService|\PHPUnit_Framework_MockObject_MockObject */
    private $permissionService;
    /** @var AssignedUsersMapper|\PHPUnit_Framework_MockObject_MockObject */
    private $assignedUsersMapper;
	/** @var BoardService|\PHPUnit_Framework_MockObject_MockObject */
	private $boardService;

    public function setUp() {
		parent::setUp();
        $this->cardMapper = $this->createMock(CardMapper::class);
        $this->stackMapper = $this->createMock(StackMapper::class);
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->boardService = $this->createMock(BoardService::class);
        $this->assignedUsersMapper = $this->createMock(AssignedUsersMapper::class);
        $this->cardService = new CardService($this->cardMapper, $this->stackMapper, $this->permissionService, $this->boardService, $this->assignedUsersMapper);
    }

    public function testFind() {
    	$card = new Card();
    	$card->setId(1337);
        $this->cardMapper->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($card);
        $this->assignedUsersMapper->expects($this->once())
			->method('find')
			->with(1337)
			->willReturn(['user1', 'user2']);
		$cardExpected = new Card();
		$cardExpected->setId(1337);
		$cardExpected->setAssignedUsers(['user1', 'user2']);
        $this->assertEquals($cardExpected, $this->cardService->find(123));
    }

    public function testCreate() {
        $card = new Card();
        $card->setTitle('Card title');
        $card->setOwner('admin');
        $card->setStackId(123);
        $card->setOrder(999);
        $card->setType('text');
        $this->cardMapper->expects($this->once())
            ->method('insert')
            ->willReturn($card);
        $b = $this->cardService->create('Card title', 123, 'text', 999, 'admin');

        $this->assertEquals($b->getTitle(), 'Card title');
        $this->assertEquals($b->getOwner(), 'admin');
        $this->assertEquals($b->getType(), 'text');
        $this->assertEquals($b->getOrder(), 999);
        $this->assertEquals($b->getStackId(), 123);
    }

    public function testDelete() {
        $this->cardMapper->expects($this->once())
            ->method('find')
            ->willReturn(new Card());
        $this->cardMapper->expects($this->once())
            ->method('delete')
            ->willReturn(1);
        $this->assertEquals(1, $this->cardService->delete(123));
    }

    public function testUpdate() {
        $card = new Card();
        $card->setTitle('title');
        $card->setArchived(false);
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function($c) { return $c; });
        $actual = $this->cardService->update(123, 'newtitle', 234, 'text', 999, 'foo', 'admin', '2017-01-01 00:00:00');
        $this->assertEquals('newtitle', $actual->getTitle());
        $this->assertEquals(234, $actual->getStackId());
        $this->assertEquals('text', $actual->getType());
        $this->assertEquals(999, $actual->getOrder());
        $this->assertEquals('foo', $actual->getDescription());
        $this->assertEquals('2017-01-01T00:00:00+00:00', $actual->getDuedate());
    }

    public function testUpdateArchived() {
        $card = new Card();
        $card->setTitle('title');
        $card->setArchived(true);
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->never())->method('update');
        $this->setExpectedException(StatusException::class);
        $this->cardService->update(123, 'newtitle', 234, 'text', 999, 'foo', 'admin', '2017-01-01 00:00:00');
    }

    public function testRename() {
        $card = new Card();
        $card->setTitle('title');
        $card->setArchived(false);
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function($c) { return $c; });
        $actual = $this->cardService->rename(123, 'newtitle');
        $this->assertEquals('newtitle', $actual->getTitle());
    }

    public function testRenameArchived() {
        $card = new Card();
        $card->setTitle('title');
        $card->setArchived(true);
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->never())->method('update');
        $this->setExpectedException(StatusException::class);
        $this->cardService->rename(123, 'newtitle');
    }

    public function dataReorder() {
        return [
            [0, 0, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]],
            [0, 9, [1, 2, 3, 4, 5, 6, 7, 8, 9, 0]],
            [1, 3, [0, 2, 3, 1, 4, 5, 6, 7, 8, 9]]
        ];
    }
    /** @dataProvider dataReorder */
    public function testReorder($cardId, $newPosition, $order) {
        $cards = $this->getCards();
        $cardsTmp = [];
        $this->cardMapper->expects($this->at(0))->method('findAll')->willReturn($cards);
        $result = $this->cardService->reorder($cardId, 123, $newPosition);
        foreach ($result as $card) {
            $actual[$card->getOrder()] = $card->getId();
        }
        $this->assertEquals($order, $actual);
    }

    private function getCards() {
        $cards = [];
        for($i=0; $i<10; $i++) {
            $cards[$i] = new Card();
            $cards[$i]->setTitle($i);
            $cards[$i]->setOrder($i);
            $cards[$i]->setId($i);
        }
        return $cards;
    }

    public function testReorderArchived() {
        $card = new Card();
        $card->setTitle('title');
        $card->setArchived(true);
        $this->cardMapper->expects($this->once())->method('findAll')->willReturn([$card]);
        $this->cardMapper->expects($this->never())->method('update')->willReturnCallback(function($c) { return $c; });
        $this->setExpectedException(StatusException::class);
        $actual = $this->cardService->reorder(123, 234, 1);
    }
    public function testArchive() {
        $card = new Card();
        $this->assertFalse($card->getArchived());
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function($c) {
            return $c;
        });
        $this->assertTrue($this->cardService->archive(123)->getArchived());
    }
    public function testUnarchive() {
        $card = new Card();
        $card->setArchived(true);
        $this->assertTrue($card->getArchived());
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->once())->method('update')->willReturnCallback(function($c) {
            return $c;
        });
        $this->assertFalse($this->cardService->unarchive(123)->getArchived());
    }

    public function testAssignLabel() {
        $card = new Card();
        $card->setArchived(false);
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->once())->method('assignLabel');
        $this->cardService->assignLabel(123, 999);
    }

    public function testAssignLabelArchived() {
        $card = new Card();
        $card->setArchived(true);
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->never())->method('assignLabel');
        $this->setExpectedException(StatusException::class);
        $this->cardService->assignLabel(123, 999);
    }

    public function testRemoveLabel() {
        $card = new Card();
        $card->setArchived(false);
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->once())->method('removeLabel');
        $this->cardService->removeLabel(123, 999);
    }

    public function testRemoveLabelArchived() {
        $card = new Card();
        $card->setArchived(true);
        $this->cardMapper->expects($this->once())->method('find')->willReturn($card);
        $this->cardMapper->expects($this->never())->method('removeLabel');
        $this->setExpectedException(StatusException::class);
        $this->cardService->removeLabel(123, 999);
    }


}