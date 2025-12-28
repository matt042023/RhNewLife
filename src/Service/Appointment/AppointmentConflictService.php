<?php

namespace App\Service\Appointment;

use App\Entity\User;
use App\Repository\RendezVousRepository;
use App\Repository\AbsenceRepository;

class AppointmentConflictService
{
    public function __construct(
        private RendezVousRepository $appointmentRepository,
        private AbsenceRepository $absenceRepository
    ) {}

    /**
     * Règle RM-05: Vérifie si un utilisateur a des conflits sur une période
     *
     * @param User $user
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @param int|null $excludeAppointmentId ID du RDV à exclure (pour édition)
     * @return bool
     */
    public function hasConflict(
        User $user,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?int $excludeAppointmentId = null
    ): bool {
        // Vérifier les conflits avec d'autres RDV confirmés
        $conflictingAppointments = $this->appointmentRepository->findConflictingAppointments(
            $user,
            $start,
            $end,
            $excludeAppointmentId
        );

        if (!empty($conflictingAppointments)) {
            return true;
        }

        // Vérifier les conflits avec des absences validées
        $conflictingAbsences = $this->absenceRepository->findConflictingAbsences(
            $user,
            $start,
            $end
        );

        return !empty($conflictingAbsences);
    }

    /**
     * Récupère la liste détaillée des conflits pour un utilisateur
     *
     * @param User $user
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return array ['appointments' => [...], 'absences' => [...]]
     */
    public function getConflicts(
        User $user,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): array {
        $conflictingAppointments = $this->appointmentRepository->findConflictingAppointments(
            $user,
            $start,
            $end
        );

        $conflictingAbsences = $this->absenceRepository->findConflictingAbsences(
            $user,
            $start,
            $end
        );

        return [
            'appointments' => $conflictingAppointments,
            'absences' => $conflictingAbsences
        ];
    }

    /**
     * Valide la disponibilité de tous les participants
     *
     * @param array $participants Liste des utilisateurs
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return array Liste des utilisateurs non disponibles avec raisons
     */
    public function validateParticipantAvailability(
        array $participants,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): array {
        $unavailable = [];

        foreach ($participants as $participant) {
            if (!$participant instanceof User) {
                continue;
            }

            if ($this->hasConflict($participant, $start, $end)) {
                $conflicts = $this->getConflicts($participant, $start, $end);

                $reasons = [];

                foreach ($conflicts['appointments'] as $appointment) {
                    $reasons[] = sprintf(
                        'RDV: %s (%s - %s)',
                        $appointment->getSubject(),
                        $appointment->getStartAt()->format('H:i'),
                        $appointment->getEndAt()->format('H:i')
                    );
                }

                foreach ($conflicts['absences'] as $absence) {
                    $typeLabel = $absence->getAbsenceType()
                        ? $absence->getAbsenceType()->getLabel()
                        : 'Absence';

                    $reasons[] = sprintf(
                        '%s (%s - %s)',
                        $typeLabel,
                        $absence->getStartAt()->format('d/m/Y H:i'),
                        $absence->getEndAt()->format('d/m/Y H:i')
                    );
                }

                $unavailable[] = [
                    'user' => $participant,
                    'reasons' => $reasons
                ];
            }
        }

        return $unavailable;
    }
}
