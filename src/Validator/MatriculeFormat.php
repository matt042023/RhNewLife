<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class MatriculeFormat extends Constraint
{
    public string $message = 'Le matricule doit être au format AAAA-#### (année sur 4 chiffres suivie d\'un tiret et d\'un numéro sur 4 chiffres).';
}
