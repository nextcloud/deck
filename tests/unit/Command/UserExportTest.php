<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Deck\Command;

use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\Service\AttachmentService;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\IAttachmentService;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserExportTest extends \Test\TestCase {

	protected $boardMapper;
	protected $boardService;
	protected $stackMapper;
	protected $cardMapper;
	protected $assignedUserMapper;
	protected $userManager;
	protected $groupManager;

	private $userExport;

	public function setUp() {
		parent::setUp();
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->boardService= $this->createMock(BoardService::class);
		$this->stackMapper= $this->createMock(StackMapper::class);
		$this->cardMapper= $this->createMock(CardMapper::class);
		$this->assignedUserMapper= $this->createMock(AssignedUsersMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userExport = new UserExport($this->boardMapper, $this->boardService, $this->stackMapper, $this->cardMapper, $this->assignedUserMapper, $this->userManager, $this->groupManager);
	}

	public function getBoard($id) {
		$board = new Board();
		$board->setId($id);
		$board->setTitle('Board ' . $id);
		return $board;
	}
	public function getStack($id) {
		$stack = new Stack();
		$stack->setId($id);
		$stack->setTitle('Stack ' . $id);
		return $stack;
	}
	public function getCard($id) {
		$card = new Card();
		$card->setId($id);
		$card->setTitle('Card ' . $id);
		return $card;
	}
	public function testExecute() {
		$input = $this->createMock(InputInterface::class);
		$input->expects($this->once())->method('getArgument')->with('user-id')->willReturn('admin');
		$output = $this->createMock(OutputInterface::class);

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('admin')
			->willReturn($user);

		$groups = [];
		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn($groups);

		$boards = [
			$this->getBoard(1),
			$this->getBoard(2),
		];
		$this->boardService->expects($this->once())
			->method('findAll')
			->with([
				'user' => 'admin',
				'groups' => $groups
			])
			->willReturn($boards);
		$this->boardMapper->expects($this->exactly(count($boards)))
			->method('find')
			->willReturn($boards[0]);
		$stacks = [
			$this->getStack(1),
			$this->getStack(2)
		];
		$this->stackMapper->expects($this->exactly(count($boards)))
			->method('findAll')
			->willReturn($stacks);
		$cards = [
			$this->getCard(1),
			$this->getCard(2),
			$this->getCard(3),
		];
		$this->cardMapper->expects($this->exactly(count($boards)*count($stacks)))
			->method('findAllByStack')
			->willReturn($cards);
		$this->cardMapper->expects($this->exactly(count($boards)*count($stacks)*count($cards)))
			->method('find')
			->willReturn($cards[0]);
		$this->assignedUserMapper->expects($this->exactly(count($boards)*count($stacks)*count($cards)))
			->method('find')
			->willReturn([]);
		$result = $this->invokePrivate($this->userExport, 'execute', [$input, $output]);
	}
}