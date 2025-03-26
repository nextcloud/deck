<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Command;

use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CommentService;
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
		private CommentService $commentService,
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
			if ($board->getDeletedAt() > 0) {
				continue;
			}

			$fullBoard = $this->boardMapper->find($board->getId(), true, true);
			$data[$board->getId()] = $fullBoard->jsonSerialize();
			$stacks = $this->stackMapper->findAll($board->getId());
			foreach ($stacks as $stack) {
				$data[$board->getId()]['stacks'][$stack->getId()] = $stack->jsonSerialize();
				$cards = $this->cardMapper->findAllByStack($stack->getId());
				foreach ($cards as $card) {
					if ($card->getDeletedAt() > 0) {
						continue;
					}
					$fullCard = $this->cardMapper->find($card->getId());

					$assignedUsers = $this->assignedUsersMapper->findAll($card->getId());
					$fullCard->setAssignedUsers($assignedUsers);

					$cardDetails = new CardDetails($fullCard, $fullBoard);
					$comments = $this->commentService->list($card->getId());
					$cardDetails->setCommentsCount(count($comments->getData()));

					$cardJson = $cardDetails->jsonSerialize();
					$cardJson['comments'] = $comments->getData();
					$data[$board->getId()]['stacks'][$stack->getId()]['cards'][] = $cardJson;
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
