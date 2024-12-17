<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Command;

use OCA\Deck\Service\Importer\BoardImportCommandService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BoardImport extends Command {
	public function __construct(
		private BoardImportCommandService $boardImportCommandService,
	) {
		parent::__construct();
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$allowedSystems = $this->boardImportCommandService->getAllowedImportSystems();
		$names = array_map(function ($name) {
			return '"' . $name . '"';
		}, array_column($allowedSystems, 'internalName'));
		$this
			->setName('deck:import')
			->setDescription('Import data')
			->addOption(
				'system',
				null,
				InputOption::VALUE_REQUIRED,
				'Source system for import. Available options: ' . implode(', ', $names) . '.',
				'DeckJson',
			)
			->addOption(
				'config',
				null,
				InputOption::VALUE_REQUIRED,
				'Configuration json file.',
				null
			)
			->addOption(
				'data',
				null,
				InputOption::VALUE_REQUIRED,
				'Data file to import.',
				'data.json'
			)
			->addArgument(
				'file',
				InputArgument::OPTIONAL,
				'File to import',
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this
			->boardImportCommandService
			->setInput($input)
			->setOutput($output)
			->setCommand($this)
			->import();
		$output->writeln('Done!');
		return 0;
	}
}
