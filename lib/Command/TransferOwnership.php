<?php

namespace OCA\Deck\Command;

use OCA\Deck\Service\BoardService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class TransferOwnership extends Command {
	protected $boardService;
	protected $questionHelper;

	public function __construct(BoardService $boardService, QuestionHelper $questionHelper) {
		parent::__construct();

		$this->boardService = $boardService;
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

		$board = $boardId ? $this->boardService->find($boardId) : null;

		if ($boardId !== null && $board->getOwner() !== $owner) {
			$output->writeln("$owner is not the owner of the board $boardId (" . $board->getTitle() . ")");
			return 1;
		}

		if ($boardId) {
			$output->writeln("Transfer board " . $board->getTitle() . " from ". $board->getOwner() ." to $newOwner");
		} else {
			$output->writeln("Transfer all boards from $owner to $newOwner");
		}

		$question = new ConfirmationQuestion('Do you really want to continue? (y/n) ', false);
		if (!$this->questionHelper->ask($input, $output, $question)) {
			return 1;
		}

		if ($boardId) {
			$this->boardService->transferBoardOwnership($boardId, $newOwner, $remapAssignment);
			$output->writeln("Board " . $board->getTitle() . " from ". $board->getOwner() ." transferred to $newOwner completed");
			return 0;
		}

		$this->boardService->transferOwnership($owner, $newOwner, $remapAssignment);
		$output->writeln("All boards from $owner to $newOwner transferred");

		return 0;
	}
}
