<?php

namespace App\Service\Planning;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\AffectationRepository;
use App\Service\Appointment\AppointmentService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des rendez-vous (Planning)
 *
 * @deprecated Ce service est conservé pour la compatibilité avec l'ancien système de planning.
 *             Pour les nouvelles fonctionnalités de rendez-vous (convocations, demandes, validations),
 *             utiliser App\Service\Appointment\AppointmentService
 */
class RendezVousService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlanningConflictService $conflictService,
        private AffectationRepository $affectationRepository,
        private ?AppointmentService $appointmentService = null
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

        // Si le nouveau système d'appointments est disponible, l'utiliser
        if ($this->appointmentService &&
            ($rdv->getType() === RendezVous::TYPE_CONVOCATION || $rdv->getType() === RendezVous::TYPE_DEMANDE)) {
            // Le nouveau service gère l'annulation avec suppression des absences liées
            $this->appointmentService->cancelAppointment($rdv, $rdv->getCreatedBy(), 'Supprimé');
        }

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
