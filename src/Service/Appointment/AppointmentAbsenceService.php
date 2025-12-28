<?php

namespace App\Service\Appointment;

use App\Entity\Absence;
use App\Entity\AppointmentParticipant;
use App\Entity\RendezVous;
use App\Entity\TypeAbsence;
use App\Repository\TypeAbsenceRepository;
use App\Repository\AbsenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AppointmentAbsenceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TypeAbsenceRepository $typeAbsenceRepository,
        private AbsenceRepository $absenceRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Règle RM-15-19: Crée une absence automatique pour un participant
     *
     * @param AppointmentParticipant $participant
     * @param RendezVous $appointment
     * @return Absence
     * @throws \RuntimeException
     */
    public function createAbsenceForParticipant(
        AppointmentParticipant $participant,
        RendezVous $appointment
    ): Absence {
        // Récupérer le type d'absence REUNION
        $typeAbsence = $this->getReunionType();

        // Créer l'absence
        $absence = new Absence();
        $absence->setUser($participant->getUser());
        $absence->setAbsenceType($typeAbsence);
        $absence->setStartAt($appointment->getStartAt());
        $absence->setEndAt($appointment->getCalculatedEndAt() ?? $appointment->getEndAt());
        $absence->setReason('Rendez-vous: ' . $appointment->getSubject());

        // RM-19: Statut VALIDEE (auto-validé)
        $absence->setStatus(Absence::STATUS_APPROVED);
        $absence->setValidatedBy($appointment->getOrganizer());

        // Justification non requise pour les réunions
        $absence->setJustificationStatus(Absence::JUSTIF_NOT_REQUIRED);

        // Affecte le planning si configuré
        if ($typeAbsence->isAffectsPlanning()) {
            $absence->setAffectsPlanning(true);
        }

        $this->em->persist($absence);
        $this->em->flush();

        $this->logger->info('Absence automatique créée pour RDV', [
            'absence_id' => $absence->getId(),
            'appointment_id' => $appointment->getId(),
            'user_id' => $participant->getUser()->getId()
        ]);

        return $absence;
    }

    /**
     * Règle RM-18: Supprime les absences liées à un rendez-vous
     *
     * @param RendezVous $appointment
     */
    public function removeLinkedAbsences(RendezVous $appointment): void
    {
        $removedCount = 0;

        foreach ($appointment->getAppointmentParticipants() as $participant) {
            $linkedAbsence = $participant->getLinkedAbsence();

            if ($linkedAbsence) {
                $this->em->remove($linkedAbsence);
                $participant->setLinkedAbsence(null);
                $removedCount++;
            }
        }

        if ($removedCount > 0) {
            $this->em->flush();

            $this->logger->info('Absences liées supprimées', [
                'appointment_id' => $appointment->getId(),
                'count' => $removedCount
            ]);
        }
    }

    /**
     * Récupère ou crée le type d'absence REUNION
     *
     * @return TypeAbsence
     * @throws \RuntimeException
     */
    private function getReunionType(): TypeAbsence
    {
        $typeAbsence = $this->typeAbsenceRepository->findOneBy([
            'code' => TypeAbsence::CODE_REUNION
        ]);

        if (!$typeAbsence) {
            throw new \RuntimeException(
                'Type d\'absence REUNION non trouvé. Veuillez créer ce type d\'absence dans la configuration.'
            );
        }

        return $typeAbsence;
    }
}
