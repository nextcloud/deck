<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Command;

use OCP\IConfig;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CalendarToggle extends Command {
	private IUserManager $userManager;
	private IConfig $config;

	public function __construct(IUserManager $userManager, IConfig $config) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->config = $config;
	}

	protected function configure() {
		$this
			->setName('deck:calendar-toggle')
			->setDescription('Enable or disable Deck calendar/tasks integration for all existing users. Users can still change their own setting afterwards. Only affects users that already exist at the time of execution.')
			->addOption(
				'on',
				null,
				InputOption::VALUE_NONE,
				'Enable calendar/tasks integration for all existing users (users can opt-out later)'
			)
			->addOption(
				'off',
				null,
				InputOption::VALUE_NONE,
				'Disable calendar/tasks integration for all existing users (users can opt-in later)'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$enable = $input->getOption('on');
		$disable = $input->getOption('off');
		if ($enable && $disable) {
			$output->writeln('<error>Cannot use --on and --off together.</error>');
			return 1;
		}
		if (!$enable && !$disable) {
			$output->writeln('<error>Please specify either --on or --off.</error>');
			return 1;
		}
		$value = $enable ? 'yes' : '';
		$users = $this->userManager->search('');
		$count = 0;
		foreach ($users as $user) {
			$uid = $user->getUID();
			$this->config->setUserValue($uid, 'deck', 'calendar', $value);
			$output->writeln("Set calendar integration to '" . ($enable ? 'on' : 'off') . "' for user: $uid");
			$count++;
		}
		$output->writeln("Done. Updated $count existing users.");
		return 0;
	}
}
