<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Command;

use OCA\Deck\Service\BoardExportOptions;
use OCA\Deck\Service\BoardExportService;
use OCA\Deck\Service\BoardService;
use OCP\App\IAppManager;
use OCP\DB\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UserExport extends Command {
	public function __construct(
		private IAppManager $appManager,
		private BoardService $boardService,
		private BoardExportService $boardExportService,
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
			->addOption(
				'no-attachments',
				null,
				InputOption::VALUE_NONE,
				'Skip attachment contents, which keeps the export small but makes it incomplete'
			)
		;
	}

	/**
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument('user-id');
		$legacyFormat = $input->getOption('legacy-format');

		$this->boardService->setUserId($userId);
		$this->boardExportService->setUserId($userId);

		$options = new BoardExportOptions(
			includeAttachments: !$input->getOption('no-attachments'),
		);
		$data = $this->boardExportService->exportBoards(
			$this->boardService->findAll(fullDetails: false),
			$options,
		);

		$output->writeln(json_encode(
			$legacyFormat ? $data : [
				'version' => $this->appManager->getAppVersion('deck'),
				'boards' => $data
			],
			JSON_PRETTY_PRINT));
		return 0;
	}
}
