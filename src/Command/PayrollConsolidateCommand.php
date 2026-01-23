<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\Payroll\PayrollConsolidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de consolidation mensuelle des données de paie
 *
 * Usage:
 * - php bin/console app:payroll:consolidate           # Consolide le mois précédent
 * - php bin/console app:payroll:consolidate 2025 1    # Consolide janvier 2025
 * - php bin/console app:payroll:consolidate --refresh # Rafraîchit les consolidations existantes
 *
 * CRON: 0 1 1 * * (1er de chaque mois à 01:00)
 */
#[AsCommand(
    name: 'app:payroll:consolidate',
    description: 'Consolide les données de paie pour un mois donné',
)]
class PayrollConsolidateCommand extends Command
{
    public function __construct(
        private PayrollConsolidationService $consolidationService,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('year', InputArgument::OPTIONAL, 'Année (par défaut: année du mois précédent)')
            ->addArgument('month', InputArgument::OPTIONAL, 'Mois (par défaut: mois précédent)')
            ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Rafraîchit les consolidations existantes')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'ID utilisateur spécifique')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans persister');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Déterminer la période
        $now = new \DateTime();
        $previousMonth = (clone $now)->modify('-1 month');

        $year = $input->getArgument('year') ? (int) $input->getArgument('year') : (int) $previousMonth->format('Y');
        $month = $input->getArgument('month') ? (int) $input->getArgument('month') : (int) $previousMonth->format('n');

        $period = sprintf('%04d-%02d', $year, $month);
        $io->title(sprintf('Consolidation paie - %s', $period));

        $refresh = $input->getOption('refresh');
        $dryRun = $input->getOption('dry-run');
        $userId = $input->getOption('user');

        if ($dryRun) {
            $io->warning('Mode simulation activé - aucune donnée ne sera persistée');
        }

        try {
            // Déterminer les utilisateurs à traiter
            if ($userId) {
                $user = $this->userRepository->find($userId);
                if (!$user) {
                    $io->error(sprintf('Utilisateur #%d non trouvé', $userId));
                    return Command::FAILURE;
                }
                $users = [$user];
            } else {
                // Récupérer tous les éducateurs actifs
                $users = $this->userRepository->findByRole('ROLE_EDUCATOR');
            }

            $io->info(sprintf('%d utilisateur(s) à traiter', count($users)));

            $created = 0;
            $refreshed = 0;
            $errors = [];

            $io->progressStart(count($users));

            foreach ($users as $user) {
                try {
                    if ($dryRun) {
                        $io->progressAdvance();
                        continue;
                    }

                    $consolidation = $this->consolidationService->consolidateForUser($user, $year, $month);

                    if ($refresh && !$consolidation->isDraft()) {
                        // Rafraîchir seulement les brouillons par défaut
                        $io->progressAdvance();
                        continue;
                    }

                    if ($refresh) {
                        $this->consolidationService->refreshConsolidation($consolidation);
                        $refreshed++;
                    } else {
                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors[] = sprintf('%s: %s', $user->getFullName(), $e->getMessage());
                    $this->logger->error('Erreur consolidation paie', [
                        'user_id' => $user->getId(),
                        'period' => $period,
                        'error' => $e->getMessage(),
                    ]);
                }

                $io->progressAdvance();
            }

            $io->progressFinish();

            // Résumé
            $io->section('Résumé');

            if ($dryRun) {
                $io->success(sprintf('Simulation terminée - %d utilisateur(s) auraient été traités', count($users)));
            } else {
                $io->success(sprintf(
                    'Consolidation terminée : %d créée(s), %d rafraîchie(s)',
                    $created,
                    $refreshed
                ));
            }

            if (!empty($errors)) {
                $io->warning(sprintf('%d erreur(s) rencontrée(s):', count($errors)));
                foreach ($errors as $error) {
                    $io->writeln('  - ' . $error);
                }
            }

            $this->logger->info('Commande consolidation paie terminée', [
                'period' => $period,
                'created' => $created,
                'refreshed' => $refreshed,
                'errors' => count($errors),
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur fatale: ' . $e->getMessage());
            $this->logger->error('Erreur fatale consolidation paie', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
