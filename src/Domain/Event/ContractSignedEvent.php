<?php

namespace App\Domain\Event;

use App\Entity\Contract;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatché quand un contrat est signé
 */
class ContractSignedEvent extends Event
{
    public const NAME = 'contract.signed';

    public function __construct(
        private Contract $contract
    ) {
    }

    public function getContract(): Contract
    {
        return $this->contract;
    }
}
