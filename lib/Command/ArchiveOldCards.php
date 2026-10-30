<?php

declare(strict_types=1);

namespace OCA\Deck\Command;

use DateTime;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ArchiveOldCards extends Command {
    private IDBConnection $db;
    private CardMapper $cardMapper;

    public function __construct(IDBConnection $db, CardMapper $cardMapper) {
        parent::__construct();
        $this->db = $db;
        $this->cardMapper = $cardMapper;
    }

    protected function configure(): void {
        $this->setName('deck:cards:archive-old')
            ->setDescription('Archive Deck cards older than a specified number of days')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of days of inactivity before archiving',
                60
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'List cards that would be archived without modifying them'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $isDryRun = (bool)$input->getOption('dry-run');
        $days = (int)$input->getOption('days');

        if ($days <= 0) {
            $output->writeln('<error>The --days parameter must be a positive integer.</error>');
            return Command::INVALID;
        }

        $cutoffTimestamp = (new DateTime("-{$days} days"))->getTimestamp();

        $output->writeln("<info>Searching for active cards unchanged for at least {$days} days (before " . date('Y-m-d H:i:s', $cutoffTimestamp) . ")...</info>");

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('deck_cards')
            ->where($qb->expr()->eq('archived', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->lt('last_modified', $qb->createNamedParameter($cutoffTimestamp, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $count = 0;

        while ($row = $result->fetch()) {
            $count++;
            $cardTitle = $row['title'] ?? 'Untitled';
            $cardId = $row['id'];

            if ($isDryRun) {
                $output->writeln("[Dry Run] Would archive card ID {$cardId}: {$cardTitle}");
                continue;
            }

            /** @var Card $card */
            $card = $this->cardMapper->createEntity($row);
            $card->setArchived(true);
            $card->setLastModified(time());

            $this->cardMapper->update($card);
            $output->writeln("Archived card ID {$cardId}: {$cardTitle}");
        }

        $result->closeCursor();
        $output->writeln("<info>Finished. Total cards processed: {$count}</info>");

        return Command::SUCCESS;
    }
}
