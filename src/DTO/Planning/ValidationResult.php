<?php

namespace App\DTO\Planning;

class ValidationResult
{
    private bool $isValid;
    private array $errors = [];
    private array $warnings = [];

    public function __construct(bool $isValid = true, array $errors = [], array $warnings = [])
    {
        $this->isValid = $isValid;
        $this->errors = $errors;
        $this->warnings = $warnings;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function setValid(bool $isValid): self
    {
        $this->isValid = $isValid;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function addError(string $type, string $message, string $severity = 'error'): self
    {
        $this->errors[] = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
        ];
        $this->isValid = false;
        return $this;
    }

    public function addWarning(string $type, string $message, string $severity = 'warning'): self
    {
        $this->warnings[] = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity, // 'info', 'warning', 'error'
        ];
        return $this;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
