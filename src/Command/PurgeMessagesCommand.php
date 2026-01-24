<?php

namespace App\Command;

use App\Repository\MessageInterneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge messages older than 12 months (R4)
 * CRON: 0 4 1 * * php bin/console app:messages:purge
 */
#[AsCommand(
    name: 'app:messages:purge',
    description: 'Purge internal messages older than 12 months',
)]
class PurgeMessagesCommand extends Command
{
    public function __construct(
        private MessageInterneRepository $repository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('months', 'm', InputOption::VALUE_REQUIRED, 'Months to keep messages', 12)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $months = (int) $input->getOption('months');
        $dryRun = $input->getOption('dry-run');

        $cutoffDate = (new \DateTime())->modify("-{$months} months");

        $io->title('Purge des messages internes');
        $io->text(sprintf('Date limite: %s (%d mois)', $cutoffDate->format('Y-m-d H:i:s'), $months));

        $messages = $this->repository->findOlderThan($cutoffDate);
        $count = count($messages);

        $io->info(sprintf('Messages trouves: %d', $count));

        if ($count === 0) {
            $io->success('Aucun message a purger.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('Mode simulation - aucun message supprime.');
            $io->table(
                ['ID', 'Sujet', 'Expediteur', 'Date envoi', 'Destinataires'],
                array_map(fn($m) => [
                    $m->getId(),
                    substr($m->getSujet(), 0, 30),
                    $m->getExpediteur()->getFullName(),
                    $m->getDateEnvoi()->format('Y-m-d'),
                    $m->getDestinatairesCount(),
                ], array_slice($messages, 0, 10))
            );
            if ($count > 10) {
                $io->text(sprintf('... et %d autres', $count - 10));
            }
            return Command::SUCCESS;
        }

        $deleted = $this->repository->deleteOlderThan($cutoffDate);

        $io->success(sprintf('%d message(s) supprime(s).', $deleted));

        return Command::SUCCESS;
    }
}
