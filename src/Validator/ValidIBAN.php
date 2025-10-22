<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidIBAN extends Constraint
{
    public string $message = 'Le format de l\'IBAN n\'est pas valide.';
    public ?string $country = null; // 'FR' pour forcer IBAN français

    public function __construct(
        ?string $country = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->country = $country ?? $this->country;
        $this->message = $message ?? $this->message;
    }
}
