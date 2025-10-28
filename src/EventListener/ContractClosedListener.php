<?php

namespace App\EventListener;

use App\Domain\Event\ContractClosedEvent;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Listener pour l'event ContractClosed
 * Actions:
 * - Log de l'événement
 * - Désactivation des accès si dernier contrat
 * - Déclenchement du processus d'offboarding
 * - Notification RH
 */
#[AsEventListener(event: ContractClosedEvent::NAME)]
class ContractClosedListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ContractClosedEvent $event): void
    {
        $contract = $event->getContract();
        $user = $contract->getUser();
        $reason = $event->getReason();

        $this->logger->info('Contract closed', [
            'contract_id' => $contract->getId(),
            'user_id' => $user->getId(),
            'user_name' => $user->getFullName(),
            'reason' => $reason,
            'user_status' => $user->getStatus(),
        ]);

        // Si l'utilisateur est maintenant archivé (plus aucun contrat actif)
        if ($user->getStatus() === User::STATUS_ARCHIVED) {
            $this->logger->info('User archived after contract closure', [
                'user_id' => $user->getId(),
            ]);

            // TODO: Démarrer le processus d'offboarding
            // - Désactivation des accès systèmes
            // - Récupération du matériel
            // - Sortie de la paie
            // - etc.

            // TODO: Notifier RH de la clôture
        }
    }
}
