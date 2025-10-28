<?php

namespace App\Validator;

use App\Entity\Contract;
use App\Repository\ContractRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UniqueActiveContractValidator extends ConstraintValidator
{
    public function __construct(
        private ContractRepository $contractRepository
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueActiveContract) {
            throw new UnexpectedTypeException($constraint, UniqueActiveContract::class);
        }

        if (!$value instanceof Contract) {
            throw new UnexpectedValueException($value, Contract::class);
        }

        // Si c'est un brouillon, on ne vérifie pas
        if ($value->getStatus() === Contract::STATUS_DRAFT) {
            return;
        }

        // Si c'est un avenant, on ne vérifie pas (le parent sera terminé lors de la validation)
        if ($value->isAmendment()) {
            return;
        }

        $user = $value->getUser();
        if (!$user) {
            return;
        }

        // Vérifier s'il existe déjà un contrat actif pour cet utilisateur
        $activeContract = $this->contractRepository->findActiveContract($user);

        // Si un contrat actif existe et que ce n'est pas le contrat actuel
        if ($activeContract && $activeContract->getId() !== $value->getId()) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
