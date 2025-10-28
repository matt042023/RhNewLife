<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ContractType extends Constraint
{
    public string $message = 'Le type de contrat "{{ value }}" n\'est pas valide. Types acceptés : {{ types }}.';

    public array $validTypes = ['CDI', 'CDD', 'Stage', 'Alternance', 'Autre'];
}
