<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ContractDatesCoherent extends Constraint
{
    public string $endDateBeforeStartMessage = 'La date de fin ne peut pas être antérieure à la date de début.';
    public string $essaiAfterEndMessage = 'La fin de période d\'essai ne peut pas être postérieure à la date de fin du contrat.';
    public string $essaiBeforeStartMessage = 'La fin de période d\'essai ne peut pas être antérieure à la date de début.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
