<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class StrongPassword extends Constraint
{
    public string $message = 'Le mot de passe doit contenir au moins {{ minLength }} caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
    public int $minLength = 12;

    public function __construct(
        ?int $minLength = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->minLength = $minLength ?? $this->minLength;
        $this->message = $message ?? $this->message;
    }
}
