<?php

namespace App\Command;

use App\Repository\CompteurCPRepository;
use App\Repository\UserRepository;
use App\Service\Payroll\CPCounterService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de création d'une nouvelle période de référence CP
 *
 * La période de référence va du 1er juin au 31 mai.
 * Cette commande crée les nouveaux compteurs avec report des soldes.
 *
 * Usage:
 * - php bin/console app:cp:new-period             # Crée la nouvelle période
 * - php bin/console app:cp:new-period --dry-run   # Simule sans persister
 *
 * CRON: 0 0 1 6 * (1er juin à 00:00)
 */
#[AsCommand(
    name: 'app:cp:new-period',
    description: 'Crée une nouvelle période de référence CP avec report des soldes',
)]
class CPNewPeriodCommand extends Command
{
    public function __construct(
        private CPCounterService $cpCounterService,
        private UserRepository $userRepository,
        private CompteurCPRepository $compteurCPRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force la création même si la période existe')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans persister');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = new \DateTime();
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');

        // Déterminer la nouvelle période de référence
        if ($currentMonth >= 6) {
            // À partir de juin, nouvelle période = année courante - année suivante
            $newPeriod = sprintf('%d-%d', $currentYear, $currentYear + 1);
            $oldPeriod = sprintf('%d-%d', $currentYear - 1, $currentYear);
        } else {
            // Avant juin, période courante = année précédente - année courante
            $newPeriod = sprintf('%d-%d', $currentYear - 1, $currentYear);
            $oldPeriod = sprintf('%d-%d', $currentYear - 2, $currentYear - 1);
        }

        $io->title(sprintf('Nouvelle période CP: %s', $newPeriod));
        $io->info(sprintf('Période précédente: %s', $oldPeriod));

        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        if ($dryRun) {
            $io->warning('Mode simulation activé - aucune donnée ne sera persistée');
        }

        // Vérifier si c'est bien le bon moment (1er juin ou --force)
        if ($currentMonth !== 6 && !$force) {
            $io->warning('Cette commande est prévue pour être exécutée le 1er juin.');
            $io->info('Utilisez --force pour l\'exécuter à une autre date.');
            return Command::SUCCESS;
        }

        try {
            // Récupérer tous les éducateurs actifs
            $users = $this->userRepository->findByRole('ROLE_EDUCATOR');
            $io->info(sprintf('%d utilisateur(s) à traiter', count($users)));

            $created = 0;
            $skipped = 0;
            $details = [];
            $errors = [];

            $io->progressStart(count($users));

            foreach ($users as $user) {
                try {
                    // Vérifier si un compteur existe déjà pour la nouvelle période
                    $existingCounter = $this->compteurCPRepository->findOneBy([
                        'user' => $user,
                        'periodeReference' => $newPeriod,
                    ]);

                    if ($existingCounter && !$force) {
                        $skipped++;
                        $io->progressAdvance();
                        continue;
                    }

                    // Récupérer le compteur de l'ancienne période
                    $oldCounter = $this->compteurCPRepository->findOneBy([
                        'user' => $user,
                        'periodeReference' => $oldPeriod,
                    ]);

                    $soldeReport = $oldCounter ? $oldCounter->getSoldeActuel() : 0.0;

                    if ($dryRun) {
                        $details[] = [
                            'user' => $user->getFullName(),
                            'solde_report' => $soldeReport,
                        ];
                    } else {
                        // Créer le nouveau compteur
                        $newCounter = $this->cpCounterService->createNewPeriodCounter(
                            $user,
                            $newPeriod,
                            $soldeReport
                        );
                        $created++;
                        $details[] = [
                            'user' => $user->getFullName(),
                            'solde_report' => $soldeReport,
                        ];
                    }
                } catch (\Exception $e) {
                    $errors[] = sprintf('%s: %s', $user->getFullName(), $e->getMessage());
                    $this->logger->error('Erreur création nouvelle période CP', [
                        'user_id' => $user->getId(),
                        'new_period' => $newPeriod,
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
                    'Simulation terminée - %d compteur(s) seraient créés',
                    count($details)
                ));

                if ($output->isVerbose() && !empty($details)) {
                    $io->table(
                        ['Utilisateur', 'Solde reporté'],
                        array_map(fn($d) => [
                            $d['user'],
                            number_format($d['solde_report'], 2, ',', ' ') . ' j'
                        ], $details)
                    );
                }
            } else {
                $io->success(sprintf(
                    'Nouvelle période créée : %d compteur(s) créé(s), %d ignoré(s)',
                    $created,
                    $skipped
                ));
            }

            if (!empty($errors)) {
                $io->warning(sprintf('%d erreur(s) rencontrée(s):', count($errors)));
                foreach ($errors as $error) {
                    $io->writeln('  - ' . $error);
                }
            }

            $this->logger->info('Commande nouvelle période CP terminée', [
                'new_period' => $newPeriod,
                'old_period' => $oldPeriod,
                'created' => $created,
                'skipped' => $skipped,
                'errors' => count($errors),
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur fatale: ' . $e->getMessage());
            $this->logger->error('Erreur fatale nouvelle période CP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
