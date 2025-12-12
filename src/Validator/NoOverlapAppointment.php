<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Contrainte de validation pour vérifier qu'un rendez-vous ne chevauche pas d'autres RDV ou absences
 *
 * Règle Métier RM-05: Un participant ne peut pas avoir 2 RDV confirmés qui se chevauchent
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class NoOverlapAppointment extends Constraint
{
    public string $message = 'Le rendez-vous chevauche avec d\'autres rendez-vous ou absences pour les participants suivants : {{ conflicts }}.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
