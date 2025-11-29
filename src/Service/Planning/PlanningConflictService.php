<?php

namespace App\Service\Planning;

use App\Entity\Absence;
use App\Entity\Affectation;
use App\Entity\RendezVous;
use App\Repository\AbsenceRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlanningConflictService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AbsenceRepository $absenceRepository,
        private RendezVousRepository $rendezVousRepository
    ) {
    }

    public function checkAndResolveConflicts(Affectation $affectation): void
    {
        if (!$affectation->getUser()) {
            return;
        }

        // 1. Check Absences
        $absences = $this->absenceRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.status = :status')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.endAt >= :start')
            ->setParameter('user', $affectation->getUser())
            ->setParameter('status', Absence::STATUS_APPROVED) // Assuming 'approved' is the valid status
            ->setParameter('start', $affectation->getStartAt())
            ->setParameter('end', $affectation->getEndAt())
            ->getQuery()
            ->getResult();

        if (count($absences) > 0) {
            $affectation->setStatut(Affectation::STATUS_TO_REPLACE_ABSENCE);
            // In a real app, we might want to link the absence or add a comment
            return; // Priority to absence
        }

        // 2. Check RendezVous (only if impactGarde is true)
        // We need to check if the user is a participant
        $rdvs = $this->rendezVousRepository->createQueryBuilder('r')
            ->join('r.participants', 'p')
            ->where('p.id = :userId')
            ->andWhere('r.impactGarde = :impact')
            ->andWhere('r.startAt <= :end')
            ->andWhere('r.endAt >= :start')
            ->andWhere('r.statut != :cancelled')
            ->setParameter('userId', $affectation->getUser()->getId())
            ->setParameter('impact', true)
            ->setParameter('start', $affectation->getStartAt())
            ->setParameter('end', $affectation->getEndAt())
            ->setParameter('cancelled', RendezVous::STATUS_CANCELLED)
            ->getQuery()
            ->getResult();

        if (count($rdvs) > 0) {
            $affectation->setStatut(Affectation::STATUS_TO_REPLACE_RDV);
        } else {
            // If no conflicts, ensure status is valid (or revert to draft/validated)
            // This logic depends on whether we want to auto-validate or just clear the error flag.
            // For now, if it was flagged, we might want to reset it.
            if (in_array($affectation->getStatut(), [Affectation::STATUS_TO_REPLACE_ABSENCE, Affectation::STATUS_TO_REPLACE_RDV])) {
                $affectation->setStatut(Affectation::STATUS_DRAFT); // Reset to draft
            }
        }
    }
}
