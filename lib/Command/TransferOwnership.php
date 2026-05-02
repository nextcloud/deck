<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Command;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CirclesService;
use OCA\Deck\Service\PermissionService;
use OCP\IUserManager;
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
	protected $userManager;
	protected $circlesService;

	public function __construct(BoardService $boardService, BoardMapper $boardMapper, PermissionService $permissionService, QuestionHelper $questionHelper, IUserManager $userManager, CirclesService $circlesService) {
		parent::__construct();

		$this->boardService = $boardService;
		$this->boardMapper = $boardMapper;
		$this->permissionService = $permissionService;
		$this->questionHelper = $questionHelper;
		$this->userManager = $userManager;
		$this->circlesService = $circlesService;
	}

	protected function configure() {
		$this
			->setName('deck:transfer-ownership')
			->setDescription('Change owner of deck boards')
			->addArgument(
				'owner',
				InputArgument::REQUIRED,
				'Owner uid or Team (circle) ID to transfer from'
			)
			->addArgument(
				'newOwner',
				InputArgument::REQUIRED,
				'New owner uid or Team (circle) ID to transfer to'
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
			->addOption(
				'to-team',
				null,
				InputOption::VALUE_NONE,
				'Treat <newOwner> as a team ID (internally stored as a circle ID) instead of a user UID'
			)
			->addOption(
				'from-team',
				null,
				InputOption::VALUE_NONE,
				'Treat <owner> as a team ID (internally stored as a circle ID) instead of a user UID'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$owner = $input->getArgument('owner');
		$newOwner = $input->getArgument('newOwner');
		$boardId = $input->getArgument('boardId');
		$remapAssignment = $input->getOption('remap');
		$toTeam = $input->getOption('to-team');
		$fromTeam = $input->getOption('from-team');
		$ownerType = Acl::PERMISSION_TYPE_USER;
		$newOwnerType = Acl::PERMISSION_TYPE_USER;
		$teamDisplayName = null;
		if ($fromTeam) {
			$ownerType = Acl::PERMISSION_TYPE_CIRCLE;
		} else {
			$ownerUserExists = $this->userManager->userExists($owner);
			$ownerCircleExists = false;
			if ($this->circlesService->isCirclesEnabled()) {
				try {
					$ownerCircleExists = $this->circlesService->getCircle($owner) !== null;
				} catch (\Throwable $e) {
					$ownerCircleExists = false;
				}
			}

			if ($ownerUserExists && $ownerCircleExists) {
				$output->writeln('<error>Ambiguous source owner: ' . $owner . ' matches both a user and a team (circle ID). Use --from-team if you mean the team.</error>');
				return 1;
			}

			if ($ownerCircleExists && !$ownerUserExists) {
				$ownerType = Acl::PERMISSION_TYPE_CIRCLE;
			}
		}
		if ($toTeam) {
			$newOwnerType = Acl::PERMISSION_TYPE_CIRCLE;
			if ($this->circlesService->isCirclesEnabled()) {
				try {
					$circle = $this->circlesService->getCircle($newOwner);
					if ($circle !== null) {
						$teamDisplayName = $circle->getDisplayName();
					}
				} catch (\Throwable $e) {
					$teamDisplayName = null;
				}
			}
		} else {
			$userExists = $this->userManager->userExists($newOwner);
			$circleExists = false;
			$circle = null;
			if ($this->circlesService->isCirclesEnabled()) {
				try {
					$circle = $this->circlesService->getCircle($newOwner);
					$circleExists = $circle !== null;
					if ($circle !== null) {
						$teamDisplayName = $circle->getDisplayName();
					}
				} catch (\Throwable $e) {
					$circleExists = false;
				}
			}

			if ($userExists && $circleExists) {
				$output->writeln('<error>Ambiguous target: ' . $newOwner . ' matches both a user and a team (circle ID). Use --to-team to transfer to the team.</error>');
				return 1;
			}

			if ($circleExists && !$userExists) {
				$newOwnerType = Acl::PERMISSION_TYPE_CIRCLE;
				$output->writeln('<comment>Detected team target: treating ' . $newOwner . ' as team ' . ($teamDisplayName ?: $newOwner) . '.</comment>');
			}
		}
		$newOwnerLabel = $newOwnerType === Acl::PERMISSION_TYPE_CIRCLE ? 'team ' . ($teamDisplayName ?: $newOwner) : $newOwner;

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
			$output->writeln('Transfer board ' . $board->getTitle() . ' from ' . $board->getOwner() . " to $newOwnerLabel");
		} else {
			$output->writeln("Transfer all boards from $owner to $newOwnerLabel");
		}

		$question = new ConfirmationQuestion('Do you really want to continue? (y/n) ', false);
		if (!$this->questionHelper->ask($input, $output, $question)) {
			return 1;
		}

		try {
			if ($boardId) {
				$this->boardService->transferBoardOwnership($boardId, $newOwner, $remapAssignment, $newOwnerType);
				$output->writeln('<info>Board ' . $board->getTitle() . " transferred to $newOwnerLabel</info>");
				return 0;
			}

			foreach ($this->boardService->transferOwnership($owner, $newOwner, $remapAssignment, $newOwnerType, $ownerType) as $board) {
				$output->writeln(' - ' . $board->getTitle() . ' transferred');
			}
			$output->writeln("<info>All boards from $owner transferred to $newOwnerLabel</info>");
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}

		return 0;
	}
}
