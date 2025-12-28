<?php

namespace App\EventListener;

use App\Domain\Event\ContractCreatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Listener pour l'event ContractCreated
 * Actions:
 * - Log de l'événement
 * - Notification admin (optionnel)
 * - Mise à jour des métriques (optionnel)
 */
#[AsEventListener(event: ContractCreatedEvent::NAME)]
class ContractCreatedListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ContractCreatedEvent $event): void
    {
        $contract = $event->getContract();
        $user = $contract->getUser();

        $this->logger->info('Contract created', [
            'contract_id' => $contract->getId(),
            'user_id' => $user->getId(),
            'user_name' => $user->getFullName(),
            'contract_type' => $contract->getType(),
            'status' => $contract->getStatus(),
        ]);

        // TODO: Envoyer notification à l'admin
        // TODO: Mettre à jour les métriques/statistiques
    }
}
