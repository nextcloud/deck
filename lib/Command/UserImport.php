<?php
/**
 * @copyright Copyright (c) 2020 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\BoardService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IGroupManager;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserImport extends Command {

	/** @var BoardMapper  */
	protected $boardMapper;

	/** @var BoardService  */
	protected $boardService;

	/** @var CardMapper  */
	protected $cardMapper;

	/** @var LabelMapper  */
	protected $labelMapper;

	/** @var StackMapper  */
	protected $stackMapper;

	/** @var IUserManager  */
	private $userManager;

	/** @var IGroupManager  */
	private $groupManager;

	/** @var AssignedUsersMapper  */
	private $assignedUsersMapper;

	public function __construct(BoardMapper $boardMapper,
								BoardService $boardService,
								StackMapper $stackMapper,
								CardMapper $cardMapper,
								LabelMapper $labelMapper,
								AssignedUsersMapper $assignedUsersMapper,
								IUserManager $userManager,
								IGroupManager $groupManager) {
		parent::__construct();

		$this->cardMapper = $cardMapper;
		$this->boardService = $boardService;
		$this->stackMapper = $stackMapper;
		$this->labelMapper = $labelMapper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->boardMapper = $boardMapper;

		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	protected function configure() {
		$this
			->setName('deck:import')
			->setDescription('Import a JSON dump of user data')
			->addArgument(
				'user-id',
				InputArgument::REQUIRED,
				'User ID of the user to get ownership'
			)
			->addArgument(
				'input-file',
				InputArgument::REQUIRED,
				'JSON file to import'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {

		$userId = $input->getArgument('user-id');

		$this->boardService->setUserId($userId);

		$file = fopen($input->getArgument('input-file'));

		$data = json_decode(fread($file, filesize($input->getArgument('input-file'))));

		foreach ($data as $board) {
			// New Board
			$newBoard = new Board();
			$newBoard->setTitle($board['title']);
			$newBoard->setOwner($userId);
			$newBoard->setColor($board['color']);
			$this->boardMapper->insert($newBoard);

			// Import labels for board
			foreach ($board['labels'] as $label) {
				$newLabel = new Label();
				$newLabel->setTitle($label['title']);
				$newLabel->setColor($label['color']);
				$newLabel->setBoardId($label['boardId']);
				$this->labelMapper->insert($newLabel);
			}

			// Import stacks for board
			foreach ($board['stacks'] as $stack) {
				$newStack = new Stack();
				$newStack->setTitle($stack['title']);
				$newStack->setBoardId($stack['boardId']);
				$newStack->setId($stack['id']);
				$this->stackMapper->insert($newStack);

				// Import cards for stack
				foreach ($stack['cards'] as $card) {
					$newCard = new Card();
					$newCard->setTitle($label['title']);
					$newCard->setDescription($label['description']);
					$newCard->setStackId($card['stackId']);
					$newCard->setLastModified($card[''])
					$newCard->setId($card['id']);
				}
			}

		}

		$output->writeln(json_encode($data, JSON_PRETTY_PRINT));
	}
}


