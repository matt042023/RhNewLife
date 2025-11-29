<?php

namespace App\Service\Planning;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\AffectationRepository;
use Doctrine\ORM\EntityManagerInterface;

class RendezVousService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlanningConflictService $conflictService,
        private AffectationRepository $affectationRepository
    ) {
    }

    public function createRendezVous(RendezVous $rdv): void
    {
        $this->em->persist($rdv);
        $this->em->flush();

        if ($rdv->isImpactGarde()) {
            $this->updateConflictsForRdv($rdv);
        }
    }

    public function updateRendezVous(RendezVous $rdv): void
    {
        $this->em->flush();

        // Always check conflicts on update (impact might have changed, or dates)
        $this->updateConflictsForRdv($rdv);
    }

    public function deleteRendezVous(RendezVous $rdv): void
    {
        // Before deleting, we might want to resolve conflicts (remove the "TO_REPLACE_RDV" status)
        // But since the RDV is gone, the conflict service check would pass next time.
        // Ideally we should trigger a re-check on affected affectations.
        
        $this->em->remove($rdv);
        $this->em->flush();
        
        // TODO: Trigger re-check of conflicts for the participants in the time range
    }

    private function updateConflictsForRdv(RendezVous $rdv): void
    {
        foreach ($rdv->getParticipants() as $participant) {
            // Find affectations for this user overlapping with the RDV
            $affectations = $this->affectationRepository->createQueryBuilder('a')
                ->where('a.user = :user')
                ->andWhere('a.startAt <= :end')
                ->andWhere('a.endAt >= :start')
                ->setParameter('user', $participant)
                ->setParameter('start', $rdv->getStartAt())
                ->setParameter('end', $rdv->getEndAt())
                ->getQuery()
                ->getResult();

            foreach ($affectations as $affectation) {
                $this->conflictService->checkAndResolveConflicts($affectation);
            }
        }
        
        $this->em->flush();
    }
}
