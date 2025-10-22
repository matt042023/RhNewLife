<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class StrongPasswordValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrongPassword) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $errors = [];

        // Longueur minimale
        if (strlen($value) < $constraint->minLength) {
            $errors[] = sprintf('au moins %d caractères', $constraint->minLength);
        }

        // Au moins une majuscule
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = 'une lettre majuscule';
        }

        // Au moins une minuscule
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = 'une lettre minuscule';
        }

        // Au moins un chiffre
        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = 'un chiffre';
        }

        // Au moins un caractère spécial
        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $errors[] = 'un caractère spécial';
        }

        if (!empty($errors)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ minLength }}', (string) $constraint->minLength)
                ->addViolation();
        }
    }
}
