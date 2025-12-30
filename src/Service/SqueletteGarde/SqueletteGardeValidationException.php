<?php

namespace App\Service\SqueletteGarde;

class SqueletteGardeValidationException extends \Exception
{
    private array $errors;
    private array $warnings;

    public function __construct(array $errors, array $warnings = [])
    {
        $this->errors = $errors;
        $this->warnings = $warnings;

        $messages = array_map(fn($e) => $e['message'], $errors);
        parent::__construct(implode(' ', $messages));
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
