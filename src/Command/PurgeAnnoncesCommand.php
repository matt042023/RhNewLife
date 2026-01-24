<?php

namespace App\Command;

use App\Repository\AnnonceInterneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deactivate expired announcements (R3: 30 days)
 * CRON: 0 3 * * * php bin/console app:annonces:purge
 */
#[AsCommand(
    name: 'app:annonces:purge',
    description: 'Deactivate expired announcements',
)]
class PurgeAnnoncesCommand extends Command
{
    public function __construct(
        private AnnonceInterneRepository $repository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without deactivating');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Desactivation des annonces expirees');

        $expired = $this->repository->findExpired();
        $count = count($expired);

        $io->info(sprintf('Annonces expirees trouvees: %d', $count));

        if ($count === 0) {
            $io->success('Aucune annonce a desactiver.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('Mode simulation - aucune annonce desactivee.');
            $io->table(
                ['ID', 'Titre', 'Date publication', 'Date expiration'],
                array_map(fn($a) => [
                    $a->getId(),
                    substr($a->getTitre(), 0, 40),
                    $a->getDatePublication()->format('Y-m-d'),
                    $a->getDateExpiration()->format('Y-m-d'),
                ], $expired)
            );
            return Command::SUCCESS;
        }

        $deactivated = $this->repository->deactivateExpired();

        $io->success(sprintf('%d annonce(s) desactivee(s).', $deactivated));

        return Command::SUCCESS;
    }
}
