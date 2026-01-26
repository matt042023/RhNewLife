<?php

namespace App\Command;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge notifications older than 60 days (WF76)
 * CRON: 0 2 * * * php bin/console app:notifications:purge
 */
#[AsCommand(
    name: 'app:notifications:purge',
    description: 'Purge notifications older than 60 days',
)]
class PurgeNotificationsCommand extends Command
{
    public function __construct(
        private NotificationRepository $repository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Days to keep notifications', 60)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        $cutoffDate = (new \DateTime())->modify("-{$days} days");

        $io->title('Purge des notifications');
        $io->text(sprintf('Date limite: %s (%d jours)', $cutoffDate->format('Y-m-d H:i:s'), $days));

        $notifications = $this->repository->findOlderThan($cutoffDate);
        $count = count($notifications);

        $io->info(sprintf('Notifications trouvees: %d', $count));

        if ($count === 0) {
            $io->success('Aucune notification a purger.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('Mode simulation - aucune notification supprimee.');
            $io->table(
                ['ID', 'Titre', 'Date envoi', 'Lu'],
                array_map(fn($n) => [
                    $n->getId(),
                    substr($n->getTitre(), 0, 40),
                    $n->getDateEnvoi()->format('Y-m-d'),
                    $n->isLu() ? 'Oui' : 'Non',
                ], array_slice($notifications, 0, 10))
            );
            if ($count > 10) {
                $io->text(sprintf('... et %d autres', $count - 10));
            }
            return Command::SUCCESS;
        }

        $deleted = $this->repository->deleteOlderThan($cutoffDate);

        $io->success(sprintf('%d notification(s) supprimee(s).', $deleted));

        return Command::SUCCESS;
    }
}
