<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Command;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\PermissionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class TransferOwnership extends Command {
	protected $boardService;
	protected $boardMapper;
	protected $permissionService;
	protected $questionHelper;

	public function __construct(BoardService $boardService, BoardMapper $boardMapper, PermissionService $permissionService, QuestionHelper $questionHelper) {
		parent::__construct();

		$this->boardService = $boardService;
		$this->boardMapper = $boardMapper;
		$this->permissionService = $permissionService;
		$this->questionHelper = $questionHelper;
	}

	protected function configure() {
		$this
			->setName('deck:transfer-ownership')
			->setDescription('Change owner of deck boards')
			->addArgument(
				'owner',
				InputArgument::REQUIRED,
				'Owner uid'
			)
			->addArgument(
				'newOwner',
				InputArgument::REQUIRED,
				'New owner uid'
			)
			->addArgument(
				'boardId',
				InputArgument::OPTIONAL,
				'Single board ID'
			)
			->addOption(
				'remap',
				'r',
				InputOption::VALUE_NONE,
				'Reassign card details of the old owner to the new one'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$owner = $input->getArgument('owner');
		$newOwner = $input->getArgument('newOwner');
		$boardId = $input->getArgument('boardId');

		$remapAssignment = $input->getOption('remap');

		$this->boardService->setUserId($owner);
		$this->permissionService->setUserId($owner);

		try {
			$board = $boardId ? $this->boardMapper->find($boardId) : null;
		} catch (\Exception $e) {
			$output->writeln('Could not find a board for the provided id.');
			return 1;
		}

		if ($boardId !== null && $board->getOwner() !== $owner) {
			$output->writeln("$owner is not the owner of the board $boardId (" . $board->getTitle() . ')');
			return 1;
		}

		if ($boardId) {
			$output->writeln('Transfer board ' . $board->getTitle() . ' from ' . $board->getOwner() . " to $newOwner");
		} else {
			$output->writeln("Transfer all boards from $owner to $newOwner");
		}

		$question = new ConfirmationQuestion('Do you really want to continue? (y/n) ', false);
		if (!$this->questionHelper->ask($input, $output, $question)) {
			return 1;
		}

		if ($boardId) {
			$this->boardService->transferBoardOwnership($boardId, $newOwner, $remapAssignment);
			$output->writeln('<info>Board ' . $board->getTitle() . ' from ' . $board->getOwner() . " transferred to $newOwner completed</info>");
			return 0;
		}

		foreach ($this->boardService->transferOwnership($owner, $newOwner, $remapAssignment) as $board) {
			$output->writeln(' - ' . $board->getTitle() . ' transferred');
		}
		$output->writeln("<info>All boards from $owner to $newOwner transferred</info>");

		return 0;
	}
}
