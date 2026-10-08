<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Command;

use OCP\DB\Exception;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixCardDate extends Command {
	public function __construct(
		private IDBConnection $db,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('deck:fix-card-date')
			->setDescription('Fixes the date fields of cards. In some DBs, the date fields may have been stored incorrectly, causing issues with date handling. This command will attempt to fix those dates.')
			->addOption(
				'default-year',
				null,
				InputOption::VALUE_OPTIONAL,
				'Default year to use for cards with invalid dates. If not provided, the large year number will be trimmed to first 4 digits.',
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'Dry run, do not make any changes.',
			)
		;
	}

	/**
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$isDryRun = (bool)$input->getOption('dry-run');
		$defaultYear = $input->getOption('default-year');

		// Validate default year if provided
		if ($defaultYear !== null && !preg_match('/^\d{4}$/', $defaultYear)) {
			$output->writeln('<error>Invalid default year provided. It must be a 4-digit year.</error>');
			return 1;
		}

		// Fetch cards with date values and validate invalid years in PHP for DB portability.
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_cards')
			->where($qb->expr()->orX(
				$qb->expr()->isNotNull('duedate'),
				$qb->expr()->isNotNull('startdate'),
				$qb->expr()->isNotNull('done'),
			));
		$cards = $qb->executeQuery()->fetchAllAssociative();

		if (empty($cards)) {
			$output->writeln('No cards with invalid dates found.');
			return 0;
		}

		$hasInvalidCards = false;

		foreach ($cards as $card) {
			$cardId = $card['id'];
			$duedate = $card['duedate'];
			$startdate = $card['startdate'];
			$done = $card['done'];
			$hasInvalidDueDate = $duedate && $this->isInvalidDate($duedate);
			$hasInvalidStartDate = $startdate && $this->isInvalidDate($startdate);
			$hasInvalidDoneDate = $done && $this->isInvalidDate($done);

			if (!$hasInvalidDueDate && !$hasInvalidStartDate && !$hasInvalidDoneDate) {
				continue;
			}

			$hasInvalidCards = true;

			$output->writeln("Card ID: $cardId");

			if ($hasInvalidDueDate) {
				$output->writeln("  Original Due Date: $duedate");
				$duedate = $this->fixDate($duedate, $defaultYear);

				if (!$isDryRun) {
					$this->updateCardDate($cardId, 'duedate', $duedate);
				}
				$output->writeln("  Fixed Due Date: $duedate");
			}

			if ($hasInvalidStartDate) {
				$output->writeln("  Original Start Date: $startdate");
				$startdate = $this->fixDate($startdate, $defaultYear);

				if (!$isDryRun) {
					$this->updateCardDate($cardId, 'startdate', $startdate);
				}
				$output->writeln("  Fixed Start Date: $startdate");
			}

			if ($hasInvalidDoneDate) {
				$output->writeln("  Original Done Date: $done");
				$done = $this->fixDate($done, $defaultYear);

				if (!$isDryRun) {
					$this->updateCardDate($cardId, 'done', $done);
				}
				$output->writeln("  Fixed Done Date: $done");
			}
		}

		if (!$hasInvalidCards) {
			$output->writeln('No cards with invalid dates found.');
		}

		return 0;
	}

	protected function isInvalidDate(string $date): bool {
		$parts = explode('-', $date);
		$year = $parts[0];
		return $year > 9999;
	}

	protected function fixDate(string $date, ?string $defaultYear): string {
		$parts = explode('-', $date);
		$year = $parts[0];
		$fixedYear = $defaultYear;
		if (!$defaultYear) {
			$fixedYear = substr($year, 0, 4);
		}
		return str_replace($year . '-', $fixedYear . '-', $date);
	}

	protected function updateCardDate(int $cardId, string $field, string $newDate): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('deck_cards')
			->set($field, $qb->createNamedParameter($newDate))
			->set('last_modified', $qb->createNamedParameter(time()))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($cardId)));
		$qb->executeStatement();
	}
}
