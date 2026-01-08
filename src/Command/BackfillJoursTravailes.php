<?php

namespace App\Command;

use App\Repository\AffectationRepository;
use App\Service\Planning\PlanningAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-jours-travailes',
    description: 'Recalcule et remplit le champ jours_travailes pour toutes les affectations existantes'
)]
class BackfillJoursTravailes extends Command
{
    public function __construct(
        private AffectationRepository $affectationRepo,
        private PlanningAssignmentService $assignmentService,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Backfill du champ jours_travailes');

        // Récupérer toutes les affectations où jours_travailes est NULL
        $affectations = $this->affectationRepo->createQueryBuilder('a')
            ->where('a.joursTravailes IS NULL')
            ->getQuery()
            ->getResult();

        $total = count($affectations);

        if ($total === 0) {
            $io->success('Aucune affectation à traiter. Toutes les affectations ont déjà un champ jours_travailes rempli.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Trouvé %d affectation(s) à traiter', $total));

        $io->progressStart($total);

        $processed = 0;

        foreach ($affectations as $affectation) {
            $workingDays = $this->assignmentService->calculateWorkingDays($affectation);
            $affectation->setJoursTravailes((int)$workingDays);

            $processed++;

            // Flush par lots de 50 pour optimiser les performances
            if ($processed % 50 === 0) {
                $this->em->flush();
                $io->progressAdvance(50);
            }
        }

        // Flush final pour les enregistrements restants
        $this->em->flush();
        $io->progressFinish();

        $io->success(sprintf('Backfill terminé avec succès : %d affectation(s) traitée(s)', $processed));

        return Command::SUCCESS;
    }
}
