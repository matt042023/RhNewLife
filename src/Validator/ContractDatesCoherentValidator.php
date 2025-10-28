<?php

namespace App\Validator;

use App\Entity\Contract;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ContractDatesCoherentValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ContractDatesCoherent) {
            throw new UnexpectedTypeException($constraint, ContractDatesCoherent::class);
        }

        if (!$value instanceof Contract) {
            throw new UnexpectedValueException($value, Contract::class);
        }

        $startDate = $value->getStartDate();
        $endDate = $value->getEndDate();
        $essaiEndDate = $value->getEssaiEndDate();

        // Vérifier que la date de début existe
        if (!$startDate) {
            return;
        }

        // Vérifier que la date de fin est après la date de début (si elle existe)
        if ($endDate && $endDate < $startDate) {
            $this->context->buildViolation($constraint->endDateBeforeStartMessage)
                ->atPath('endDate')
                ->addViolation();
        }

        // Vérifier que la fin de période d'essai est cohérente
        if ($essaiEndDate) {
            // La fin d'essai ne peut pas être avant le début du contrat
            if ($essaiEndDate < $startDate) {
                $this->context->buildViolation($constraint->essaiBeforeStartMessage)
                    ->atPath('essaiEndDate')
                    ->addViolation();
            }

            // La fin d'essai ne peut pas être après la fin du contrat (si CDD)
            if ($endDate && $essaiEndDate > $endDate) {
                $this->context->buildViolation($constraint->essaiAfterEndMessage)
                    ->atPath('essaiEndDate')
                    ->addViolation();
            }
        }
    }
}
