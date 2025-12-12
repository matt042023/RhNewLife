<?php

namespace App\Validator;

use App\Entity\RendezVous;
use App\Service\Appointment\AppointmentConflictService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NoOverlapAppointmentValidator extends ConstraintValidator
{
    public function __construct(
        private AppointmentConflictService $conflictService
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoOverlapAppointment) {
            throw new UnexpectedTypeException($constraint, NoOverlapAppointment::class);
        }

        if (!$value instanceof RendezVous) {
            throw new UnexpectedValueException($value, RendezVous::class);
        }

        // Ne valider que les RDV confirmés
        if ($value->getStatut() !== RendezVous::STATUS_CONFIRME) {
            return;
        }

        $startAt = $value->getStartAt();
        $endAt = $value->getEndAt();

        if (!$startAt || !$endAt) {
            // Pas de dates définies, pas de conflit possible
            return;
        }

        // Récupérer tous les participants
        $participants = [];
        foreach ($value->getAppointmentParticipants() as $appointmentParticipant) {
            $participants[] = $appointmentParticipant->getUser();
        }

        if (empty($participants)) {
            return;
        }

        // Vérifier la disponibilité de chaque participant
        $unavailableParticipants = $this->conflictService->validateParticipantAvailability(
            $participants,
            $startAt,
            $endAt
        );

        if (!empty($unavailableParticipants)) {
            // Construire le message d'erreur avec les conflits détaillés
            $conflictMessages = [];

            foreach ($unavailableParticipants as $unavailable) {
                $user = $unavailable['user'];
                $reasons = $unavailable['reasons'];

                $conflictMessages[] = sprintf(
                    '%s : %s',
                    $user->getFullName(),
                    implode(', ', $reasons)
                );
            }

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ conflicts }}', implode(' | ', $conflictMessages))
                ->addViolation();
        }
    }
}
