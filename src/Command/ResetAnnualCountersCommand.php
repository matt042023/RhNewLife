<?php

namespace App\Command;

use App\Service\AnnualDayCounterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-annual-counters',
    description: 'Réinitialise les compteurs de jours annuels pour une nouvelle année',
)]
class ResetAnnualCountersCommand extends Command
{
    public function __construct(
        private AnnualDayCounterService $annualDayCounterService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('year', InputArgument::OPTIONAL, 'Année pour laquelle créer les compteurs (par défaut: année en cours)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule l\'exécution sans modifier la base de données')
            ->setHelp(<<<'HELP'
Cette commande réinitialise les compteurs de jours annuels pour tous les éducateurs ayant un contrat actif.

Elle doit être exécutée automatiquement chaque 1er janvier via un cron job:
  1 0 1 1 * docker compose exec -T php php bin/console app:reset-annual-counters

Fonctionnement:
- Trouve tous les contrats actifs utilisant le système annuel
- Crée un nouveau compteur pour l'année spécifiée
- Calcule 258 jours (ou prorata si le contrat débute en cours d'année)
- Ignore les compteurs déjà existants

Usage:
  php bin/console app:reset-annual-counters
  php bin/console app:reset-annual-counters 2026
  php bin/console app:reset-annual-counters --dry-run

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Déterminer l'année
        $year = $input->getArgument('year');
        if (!$year) {
            $year = (int)date('Y');
        } else {
            $year = (int)$year;
        }

        $isDryRun = $input->getOption('dry-run');

        $io->title(sprintf('Réinitialisation des compteurs annuels pour %d', $year));

        if ($isDryRun) {
            $io->warning('Mode DRY-RUN: Aucune modification ne sera effectuée en base de données');
        }

        $io->section('Recherche des contrats actifs...');

        if ($isDryRun) {
            $io->note('En mode dry-run, cette commande simulera la création des compteurs.');
            $io->info('Pour exécuter réellement, relancez la commande sans --dry-run');
            return Command::SUCCESS;
        }

        // Exécuter le reset
        try {
            $results = $this->annualDayCounterService->resetCountersForNewYear($year);

            $io->section('Résultats');

            $io->success(sprintf(
                '%d compteur(s) créé(s) pour l\'année %d',
                $results['created'],
                $year
            ));

            if ($results['skipped'] > 0) {
                $io->info(sprintf(
                    '%d compteur(s) ignoré(s) (déjà existant(s))',
                    $results['skipped']
                ));
            }

            if (!empty($results['errors'])) {
                $io->warning(sprintf('%d erreur(s) rencontrée(s)', count($results['errors'])));

                $io->table(
                    ['ID Contrat', 'ID Utilisateur', 'Erreur'],
                    array_map(function ($error) {
                        return [
                            $error['contract_id'],
                            $error['user_id'],
                            $error['error'],
                        ];
                    }, $results['errors'])
                );

                return Command::FAILURE;
            }

            $io->note([
                'Les compteurs ont été créés avec succès.',
                'Les éducateurs peuvent maintenant être affectés à des gardes.',
                'Le solde initial est de 258 jours (ou proratisé selon la date d\'embauche).',
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors du reset des compteurs: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
