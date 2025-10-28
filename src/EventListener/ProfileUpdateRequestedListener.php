<?php

namespace App\EventListener;

use App\Domain\Event\ProfileUpdateRequestedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Listener pour l'event ProfileUpdateRequested
 * Actions:
 * - Log de l'événement
 * - Notification admin déjà gérée dans le service
 */
#[AsEventListener(event: ProfileUpdateRequestedEvent::NAME)]
class ProfileUpdateRequestedListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ProfileUpdateRequestedEvent $event): void
    {
        $request = $event->getRequest();
        $user = $request->getUser();

        $this->logger->info('Profile update requested', [
            'request_id' => $request->getId(),
            'user_id' => $user->getId(),
            'user_name' => $user->getFullName(),
            'requested_fields' => array_keys($request->getRequestedData()),
        ]);

        // TODO: Créer une notification dans le système pour l'admin
        // TODO: Incrémenter le compteur de demandes en attente
    }
}
