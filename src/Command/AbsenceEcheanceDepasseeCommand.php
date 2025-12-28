<?php

namespace App\Command;

use App\Entity\Absence;
use App\Repository\AbsenceRepository;
use App\Service\Absence\AbsenceNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande CRON pour traiter les échéances de justificatifs dépassées
 *
 * À exécuter quotidiennement (ex: 9h00)
 * CRON: 0 9 * * * php bin/console app:absence:echeance-depassee
 */
#[AsCommand(
    name: 'app:absence:echeance-depassee',
    description: 'Alerte le service RH des échéances de justificatifs dépassées',
)]
class AbsenceEcheanceDepasseeCommand extends Command
{
    public function __construct(
        private AbsenceRepository $absenceRepository,
        private AbsenceNotificationService $notificationService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simuler l\'exécution sans envoyer d\'emails'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limiter le nombre d\'alertes envoyées',
                null
            )
            ->setHelp(
                <<<'HELP'
Cette commande vérifie les absences dont l'échéance de justificatif est dépassée
et envoie une alerte au service RH pour traiter ces situations.

Exemples d'utilisation:
  php bin/console app:absence:echeance-depassee
  php bin/console app:absence:echeance-depassee --dry-run
  php bin/console app:absence:echeance-depassee --limit=10

Configuration CRON recommandée (tous les jours à 9h):
  0 9 * * * cd /path/to/project && php bin/console app:absence:echeance-depassee
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $limit = $input->getOption('limit');

        $io->title('Traitement des Échéances de Justificatifs Dépassées');

        if ($dryRun) {
            $io->note('Mode simulation activé - aucun email ne sera envoyé');
        }

        $io->section('Recherche des échéances dépassées');

        $now = new \DateTime();
        $io->text("Date actuelle: {$now->format('d/m/Y H:i')}");

        // Find absences with overdue justification deadlines
        $qb = $this->absenceRepository->createQueryBuilder('a');
        $qb
            ->where('a.justificationStatus IN (:statuses)')
            ->andWhere('a.justificationDeadline < :now')
            ->andWhere('a.status != :cancelled')
            ->setParameter('statuses', [Absence::JUSTIF_PENDING, Absence::JUSTIF_REJECTED])
            ->setParameter('now', $now)
            ->setParameter('cancelled', Absence::STATUS_CANCELLED)
            ->orderBy('a.justificationDeadline', 'ASC');

        if ($limit) {
            $qb->setMaxResults((int) $limit);
        }

        $overdueAbsences = $qb->getQuery()->getResult();

        if (empty($overdueAbsences)) {
            $io->success('Aucune échéance dépassée à traiter');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Trouvé %d échéance(s) dépassée(s)', count($overdueAbsences)));

        // Display details
        $io->section('Détails des échéances dépassées');

        $tableData = [];
        foreach ($overdueAbsences as $absence) {
            $deadline = $absence->getJustificationDeadline();
            $daysOverdue = $now->diff($deadline)->days;

            $tableData[] = [
                $absence->getId(),
                $absence->getUser()->getFullName(),
                $absence->getAbsenceType()->getLabel(),
                $deadline->format('d/m/Y H:i'),
                $daysOverdue . ' jour' . ($daysOverdue > 1 ? 's' : ''),
                $absence->getJustificationStatus(),
            ];
        }

        $io->table(
            ['ID', 'Employé', 'Type', 'Échéance', 'Retard', 'Statut'],
            $tableData
        );

        // Send notifications
        $io->section('Envoi des alertes au service RH');

        $sentCount = 0;
        $errorCount = 0;

        $io->progressStart(count($overdueAbsences));

        foreach ($overdueAbsences as $absence) {
            $io->progressAdvance();

            if ($dryRun) {
                $io->text(sprintf(
                    '[DRY-RUN] Alerte RH pour absence #%d (%s - %s)',
                    $absence->getId(),
                    $absence->getUser()->getFullName(),
                    $absence->getAbsenceType()->getLabel()
                ), OutputInterface::VERBOSITY_VERBOSE);
                $sentCount++;
                continue;
            }

            try {
                $this->notificationService->notifyJustificationOverdue($absence);
                $sentCount++;

                $io->text(sprintf(
                    '✓ Alerte envoyée pour absence #%d',
                    $absence->getId()
                ), OutputInterface::VERBOSITY_VERBOSE);
            } catch (\Exception $e) {
                $errorCount++;
                $io->error(sprintf(
                    'Erreur lors de l\'envoi pour absence #%d: %s',
                    $absence->getId(),
                    $e->getMessage()
                ));
            }
        }

        $io->progressFinish();

        // Summary
        $io->newLine();
        $io->section('Résumé');

        $io->horizontalTable(
            ['Métrique', 'Valeur'],
            [
                ['Échéances dépassées', count($overdueAbsences)],
                ['Alertes envoyées', $sentCount],
                ['Erreurs', $errorCount],
                ['Taux de succès', count($overdueAbsences) > 0 ? round(($sentCount / count($overdueAbsences)) * 100, 2) . '%' : 'N/A'],
            ]
        );

        // Recommendations
        if (count($overdueAbsences) > 0) {
            $io->newLine();
            $io->section('Actions recommandées pour le service RH');
            $io->listing([
                'Contacter les employés concernés pour comprendre la situation',
                'Vérifier si les justificatifs peuvent être acceptés malgré le retard',
                'Appliquer les procédures disciplinaires si nécessaire',
                'Mettre à jour le statut des absences dans le système',
            ]);
        }

        if ($errorCount > 0) {
            $io->warning("$errorCount erreur(s) lors de l'envoi des alertes");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->success("Simulation terminée - $sentCount alerte(s) auraient été envoyées");
        } else {
            $io->success("$sentCount alerte(s) envoyée(s) au service RH");
        }

        return Command::SUCCESS;
    }
}
