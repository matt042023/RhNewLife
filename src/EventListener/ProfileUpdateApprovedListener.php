<?php

namespace App\EventListener;

use App\Domain\Event\ProfileUpdateApprovedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Listener pour l'event ProfileUpdateApproved
 * Actions:
 * - Log de l'événement
 * - Notification utilisateur déjà gérée dans le service
 */
#[AsEventListener(event: ProfileUpdateApprovedEvent::NAME)]
class ProfileUpdateApprovedListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ProfileUpdateApprovedEvent $event): void
    {
        $request = $event->getRequest();
        $user = $request->getUser();
        $approvedBy = $event->getApprovedBy();

        $this->logger->info('Profile update approved', [
            'request_id' => $request->getId(),
            'user_id' => $user->getId(),
            'user_name' => $user->getFullName(),
            'approved_by_id' => $approvedBy->getId(),
            'approved_by_name' => $approvedBy->getFullName(),
            'updated_fields' => array_keys($request->getRequestedData()),
        ]);

        // TODO: Créer une notification système pour l'utilisateur
        // TODO: Audit trail de la modification
    }
}
