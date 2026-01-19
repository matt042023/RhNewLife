<?php

namespace App\Command;

use App\Repository\ConsolidationPaieRepository;
use App\Repository\UserRepository;
use App\Service\Payroll\PayrollNotificationService;
use App\Service\Payroll\PayrollValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de rappel pour la validation des rapports de paie
 *
 * Envoie un email de rappel aux administrateurs si des rapports
 * sont encore en brouillon après le 3 du mois.
 *
 * Usage:
 * - php bin/console app:payroll:reminder             # Rappel pour le mois précédent
 * - php bin/console app:payroll:reminder 2025 1      # Rappel pour janvier 2025
 * - php bin/console app:payroll:reminder --dry-run   # Simule sans envoyer d'email
 *
 * CRON: 0 9 4 * * (4 de chaque mois à 09:00)
 */
#[AsCommand(
    name: 'app:payroll:reminder',
    description: 'Envoie un rappel de validation des rapports de paie',
)]
class PayrollReminderCommand extends Command
{
    public function __construct(
        private PayrollValidationService $validationService,
        private PayrollNotificationService $notificationService,
        private ConsolidationPaieRepository $consolidationRepository,
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
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Envoyer même si tous les rapports sont validés')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans envoyer d\'email');
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

        $monthNames = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        $io->title(sprintf('Rappel validation paie - %s %d', $monthNames[$month], $year));

        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        if ($dryRun) {
            $io->warning('Mode simulation activé - aucun email ne sera envoyé');
        }

        try {
            // Récupérer les statistiques du mois
            $stats = $this->validationService->getMonthStats($period);

            $io->section('État des rapports');
            $io->listing([
                sprintf('Total: %d', $stats['total']),
                sprintf('Validés: %d', $stats['validated']),
                sprintf('En attente: %d', $stats['draft']),
                sprintf('Taux de complétion: %.1f%%', $stats['completion_rate']),
            ]);

            // Vérifier s'il y a des rapports en attente
            if ($stats['draft'] === 0 && !$force) {
                $io->success('Tous les rapports sont déjà validés. Aucun rappel nécessaire.');
                return Command::SUCCESS;
            }

            // Récupérer les utilisateurs avec des rapports en attente
            $pendingConsolidations = $this->consolidationRepository->findByPeriodAndStatus(
                $period,
                'draft'
            );

            $pendingUsers = [];
            foreach ($pendingConsolidations as $consolidation) {
                $user = $consolidation->getUser();
                if ($user) {
                    $pendingUsers[] = $user;
                }
            }

            $io->info(sprintf('%d rapport(s) en attente de validation', count($pendingUsers)));

            if ($output->isVerbose()) {
                $io->listing(array_map(
                    fn($u) => sprintf('%s (%s)', $u->getFullName(), $u->getMatricule() ?? 'N/A'),
                    $pendingUsers
                ));
            }

            // Récupérer les administrateurs RH
            $admins = $this->userRepository->findByRole('ROLE_ADMIN');

            if (empty($admins)) {
                $io->warning('Aucun administrateur trouvé pour recevoir le rappel.');
                return Command::SUCCESS;
            }

            $io->info(sprintf('%d administrateur(s) seront notifié(s)', count($admins)));

            // Envoyer les rappels
            if ($dryRun) {
                $io->success(sprintf(
                    'Simulation terminée - %d email(s) auraient été envoyés',
                    count($admins)
                ));
            } else {
                $sent = 0;
                $errors = [];

                foreach ($admins as $admin) {
                    try {
                        $this->notificationService->sendValidationReminder(
                            $admin,
                            $period,
                            $pendingUsers
                        );
                        $sent++;
                    } catch (\Exception $e) {
                        $errors[] = sprintf('%s: %s', $admin->getEmail(), $e->getMessage());
                        $this->logger->error('Erreur envoi rappel validation', [
                            'admin_id' => $admin->getId(),
                            'period' => $period,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $io->success(sprintf('Rappel envoyé à %d administrateur(s)', $sent));

                if (!empty($errors)) {
                    $io->warning(sprintf('%d erreur(s) rencontrée(s):', count($errors)));
                    foreach ($errors as $error) {
                        $io->writeln('  - ' . $error);
                    }
                }
            }

            $this->logger->info('Commande rappel validation paie terminée', [
                'period' => $period,
                'pending' => $stats['draft'],
                'admins_notified' => $dryRun ? 0 : count($admins),
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur fatale: ' . $e->getMessage());
            $this->logger->error('Erreur fatale rappel validation paie', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
