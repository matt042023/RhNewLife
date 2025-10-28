<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueActiveContract extends Constraint
{
    public string $message = 'Cet utilisateur a déjà un contrat actif.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
