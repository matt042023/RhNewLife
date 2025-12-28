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
 * Commande CRON pour envoyer des rappels J-1 pour les justificatifs d'absence
 *
 * À exécuter quotidiennement, de préférence le matin (ex: 8h00)
 * CRON: 0 8 * * * php bin/console app:absence:rappel-justificatif
 */
#[AsCommand(
    name: 'app:absence:rappel-justificatif',
    description: 'Envoie des rappels J-1 aux employés devant fournir un justificatif d\'absence',
)]
class AbsenceRappelJustificatifCommand extends Command
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
                'hours',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre d\'heures avant l\'échéance pour envoyer le rappel',
                24
            )
            ->setHelp(
                <<<'HELP'
Cette commande envoie des rappels par email aux employés qui doivent fournir
un justificatif d'absence et dont l'échéance arrive bientôt (par défaut 24h).

Exemples d'utilisation:
  php bin/console app:absence:rappel-justificatif
  php bin/console app:absence:rappel-justificatif --dry-run
  php bin/console app:absence:rappel-justificatif --hours=48

Configuration CRON recommandée (tous les jours à 8h):
  0 8 * * * cd /path/to/project && php bin/console app:absence:rappel-justificatif
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $hours = (int) $input->getOption('hours');

        $io->title('Rappel Justificatifs d\'Absence');

        if ($dryRun) {
            $io->note('Mode simulation activé - aucun email ne sera envoyé');
        }

        $io->section("Recherche des absences avec échéance dans les {$hours}h");

        // Calculate deadline window
        $now = new \DateTime();
        $windowStart = (clone $now)->modify("+{$hours} hours");
        $windowEnd = (clone $windowStart)->modify('+1 hour');

        $io->text([
            "Date actuelle: {$now->format('d/m/Y H:i')}",
            "Fenêtre de rappel: {$windowStart->format('d/m/Y H:i')} - {$windowEnd->format('d/m/Y H:i')}",
        ]);

        // Find absences requiring justification with deadline in window
        $qb = $this->absenceRepository->createQueryBuilder('a');
        $qb
            ->where('a.justificationStatus IN (:statuses)')
            ->andWhere('a.justificationDeadline >= :windowStart')
            ->andWhere('a.justificationDeadline < :windowEnd')
            ->andWhere('a.status != :cancelled')
            ->setParameter('statuses', [Absence::JUSTIF_PENDING, Absence::JUSTIF_REJECTED])
            ->setParameter('windowStart', $windowStart)
            ->setParameter('windowEnd', $windowEnd)
            ->setParameter('cancelled', Absence::STATUS_CANCELLED)
            ->orderBy('a.justificationDeadline', 'ASC');

        $absences = $qb->getQuery()->getResult();

        if (empty($absences)) {
            $io->success('Aucun rappel à envoyer');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Trouvé %d absence(s) nécessitant un rappel', count($absences)));

        // Send reminders
        $sentCount = 0;
        $errorCount = 0;

        $io->progressStart(count($absences));

        foreach ($absences as $absence) {
            $user = $absence->getUser();
            $deadline = $absence->getJustificationDeadline();

            $io->progressAdvance();

            if ($dryRun) {
                $io->text(sprintf(
                    '[DRY-RUN] Rappel pour %s (ID: %d, Échéance: %s)',
                    $user->getFullName(),
                    $absence->getId(),
                    $deadline->format('d/m/Y H:i')
                ));
                $sentCount++;
                continue;
            }

            try {
                $this->notificationService->notifyJustificationDeadlineReminder($absence);
                $sentCount++;

                $io->text(sprintf(
                    '✓ Rappel envoyé à %s (Absence #%d)',
                    $user->getEmail(),
                    $absence->getId()
                ), OutputInterface::VERBOSITY_VERBOSE);
            } catch (\Exception $e) {
                $errorCount++;
                $io->error(sprintf(
                    'Erreur lors de l\'envoi à %s (Absence #%d): %s',
                    $user->getEmail(),
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
                ['Absences trouvées', count($absences)],
                ['Rappels envoyés', $sentCount],
                ['Erreurs', $errorCount],
                ['Taux de succès', count($absences) > 0 ? round(($sentCount / count($absences)) * 100, 2) . '%' : 'N/A'],
            ]
        );

        if ($errorCount > 0) {
            $io->warning("$errorCount erreur(s) lors de l'envoi des rappels");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->success("Simulation terminée - $sentCount rappel(s) auraient été envoyés");
        } else {
            $io->success("$sentCount rappel(s) envoyé(s) avec succès");
        }

        return Command::SUCCESS;
    }
}
