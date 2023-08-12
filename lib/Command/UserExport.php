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

use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\Service\BoardService;
use OCP\App\IAppManager;
use OCP\DB\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserExport extends Command {
	public function __construct(
		private IAppManager $appManager,
		private BoardMapper $boardMapper,
		private BoardService $boardService,
		private StackMapper $stackMapper,
		private CardMapper $cardMapper,
		private AssignmentMapper $assignedUsersMapper,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('deck:export')
			->setDescription('Export a JSON dump of user data')
			->addArgument(
				'user-id',
				InputArgument::REQUIRED,
				'User ID of the user'
			)
			->addOption('legacy-format', 'l')
		;
	}

	/**
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument('user-id');
		$legacyFormat = $input->getOption('legacy-format');

		$this->boardService->setUserId($userId);
		$boards = $this->boardService->findAll(fullDetails: false);

		$data = [];
		foreach ($boards as $board) {
			$fullBoard = $this->boardMapper->find($board->getId(), true, true);
			$data[$board->getId()] = $fullBoard->jsonSerialize();
			$stacks = $this->stackMapper->findAll($board->getId());
			foreach ($stacks as $stack) {
				$data[$board->getId()]['stacks'][$stack->getId()] = $stack->jsonSerialize();
				$cards = $this->cardMapper->findAllByStack($stack->getId());
				foreach ($cards as $card) {
					$fullCard = $this->cardMapper->find($card->getId());
					$assignedUsers = $this->assignedUsersMapper->findAll($card->getId());
					$fullCard->setAssignedUsers($assignedUsers);

					$cardDetails = new CardDetails($fullCard, $fullBoard);
					$data[$board->getId()]['stacks'][$stack->getId()]['cards'][] = $cardDetails->jsonSerialize();
				}
			}
		}
		$output->writeln(json_encode(
			$legacyFormat ? $data : [
				'version' => $this->appManager->getAppVersion('deck'),
				'boards' => $data
			],
			JSON_PRETTY_PRINT));
		return 0;
	}
}
