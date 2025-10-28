<?php

namespace App\Domain\Event;

use App\Entity\Contract;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatché quand un contrat est clôturé
 */
class ContractClosedEvent extends Event
{
    public const NAME = 'contract.closed';

    public function __construct(
        private Contract $contract,
        private string $reason
    ) {
    }

    public function getContract(): Contract
    {
        return $this->contract;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
