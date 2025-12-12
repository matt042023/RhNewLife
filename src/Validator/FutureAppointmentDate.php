<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Contrainte de validation pour vérifier que la date d'un rendez-vous est dans le futur
 *
 * Règle Métier RM-02: La date du rendez-vous doit être dans le futur
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class FutureAppointmentDate extends Constraint
{
    public string $message = 'La date du rendez-vous doit être dans le futur.';

    public bool $allowPast = false; // Pour l'historique ou édition d'anciens RDV
}
