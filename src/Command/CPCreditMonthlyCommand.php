<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\Payroll\CPCounterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de crédit mensuel des congés payés (2.5j/mois)
 *
 * Usage:
 * - php bin/console app:cp:credit-monthly             # Crédite le mois courant
 * - php bin/console app:cp:credit-monthly 2025 1      # Crédite janvier 2025
 * - php bin/console app:cp:credit-monthly --dry-run   # Simule sans persister
 *
 * CRON: 0 0 1 * * (1er de chaque mois à 00:30)
 */
#[AsCommand(
    name: 'app:cp:credit-monthly',
    description: 'Crédite les CP mensuels (2.5j/mois) à tous les éducateurs actifs',
)]
class CPCreditMonthlyCommand extends Command
{
    public function __construct(
        private CPCounterService $cpCounterService,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('year', InputArgument::OPTIONAL, 'Année (par défaut: année courante)')
            ->addArgument('month', InputArgument::OPTIONAL, 'Mois (par défaut: mois courant)')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'ID utilisateur spécifique')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans persister');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Déterminer la période
        $now = new \DateTime();
        $year = $input->getArgument('year') ? (int) $input->getArgument('year') : (int) $now->format('Y');
        $month = $input->getArgument('month') ? (int) $input->getArgument('month') : (int) $now->format('n');

        $monthNames = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        $io->title(sprintf('Crédit CP mensuel - %s %d', $monthNames[$month], $year));

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
                $users = $this->userRepository->findByRole('ROLE_EDUCATOR');
            }

            $io->info(sprintf('%d utilisateur(s) à traiter', count($users)));

            $credited = 0;
            $totalCP = 0.0;
            $details = [];
            $errors = [];

            $io->progressStart(count($users));

            foreach ($users as $user) {
                try {
                    if ($dryRun) {
                        // Calculer le prorata sans persister
                        $prorata = $this->cpCounterService->calculateProrata($user, $year, $month);
                        $cpToCredit = 2.5 * $prorata;
                        $details[] = [
                            'user' => $user->getFullName(),
                            'prorata' => $prorata,
                            'cp' => $cpToCredit,
                        ];
                        $totalCP += $cpToCredit;
                    } else {
                        $cpCredited = $this->cpCounterService->creditMonthlyCP($user, $year, $month);
                        if ($cpCredited > 0) {
                            $credited++;
                            $totalCP += $cpCredited;
                            $details[] = [
                                'user' => $user->getFullName(),
                                'cp' => $cpCredited,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = sprintf('%s: %s', $user->getFullName(), $e->getMessage());
                    $this->logger->error('Erreur crédit CP mensuel', [
                        'user_id' => $user->getId(),
                        'year' => $year,
                        'month' => $month,
                        'error' => $e->getMessage(),
                    ]);
                }

                $io->progressAdvance();
            }

            $io->progressFinish();

            // Résumé
            $io->section('Résumé');

            if ($dryRun) {
                $io->success(sprintf(
                    'Simulation terminée - %.2f jours seraient crédités à %d utilisateur(s)',
                    $totalCP,
                    count($users)
                ));

                // Afficher le détail en mode verbose
                if ($output->isVerbose() && !empty($details)) {
                    $io->table(
                        ['Utilisateur', 'Prorata', 'CP à créditer'],
                        array_map(fn($d) => [
                            $d['user'],
                            isset($d['prorata']) ? number_format($d['prorata'] * 100, 0) . '%' : '100%',
                            number_format($d['cp'], 2, ',', ' ') . ' j'
                        ], $details)
                    );
                }
            } else {
                $io->success(sprintf(
                    'Crédit terminé : %.2f jours crédités à %d utilisateur(s)',
                    $totalCP,
                    $credited
                ));
            }

            if (!empty($errors)) {
                $io->warning(sprintf('%d erreur(s) rencontrée(s):', count($errors)));
                foreach ($errors as $error) {
                    $io->writeln('  - ' . $error);
                }
            }

            $this->logger->info('Commande crédit CP mensuel terminée', [
                'year' => $year,
                'month' => $month,
                'credited' => $credited,
                'total_cp' => $totalCP,
                'errors' => count($errors),
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur fatale: ' . $e->getMessage());
            $this->logger->error('Erreur fatale crédit CP mensuel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
