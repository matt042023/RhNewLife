<?php

namespace App\Domain\Event;

use App\Entity\Contract;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatché quand un nouveau contrat est créé
 */
class ContractCreatedEvent extends Event
{
    public const NAME = 'contract.created';

    public function __construct(
        private Contract $contract
    ) {
    }

    public function getContract(): Contract
    {
        return $this->contract;
    }
}
