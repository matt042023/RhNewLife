<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class FutureAppointmentDateValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof FutureAppointmentDate) {
            throw new UnexpectedTypeException($constraint, FutureAppointmentDate::class);
        }

        // Null values are handled by NotNull or NotBlank constraints
        if (null === $value) {
            return;
        }

        if (!$value instanceof \DateTimeInterface) {
            throw new UnexpectedValueException($value, \DateTimeInterface::class);
        }

        // Si allowPast est true, on ne valide pas (pour édition d'anciens RDV)
        if ($constraint->allowPast) {
            return;
        }

        $now = new \DateTime();

        if ($value <= $now) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
