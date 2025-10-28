<?php

namespace App\Service;

use App\Domain\Event\ProfileUpdateRequestedEvent;
use App\Domain\Event\ProfileUpdateApprovedEvent;
use App\Entity\ProfileUpdateRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des demandes de modification de profil
 * Responsabilités:
 * - Création des demandes de modification
 * - Approbation des demandes
 * - Rejet des demandes
 * - Notifications par email
 */
class ProfileUpdateRequestManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserManager $userManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
        private string $senderEmail = 'noreply@rhnewlife.fr',
        private string $senderName = 'RH NewLife',
        private string $adminEmail = 'admin@rhnewlife.fr'
    ) {
    }

    /**
     * Crée une nouvelle demande de modification de profil
     * L'utilisateur soumet les nouvelles données qu'il souhaite modifier
     *
     * @param User $user L'utilisateur qui fait la demande
     * @param array $requestedData Les données à modifier (ex: ['phone' => '0123456789', 'address' => '...'])
     * @param string|null $reason Raison de la demande (optionnel)
     * @return ProfileUpdateRequest
     */
    public function createRequest(User $user, array $requestedData, ?string $reason = null): ProfileUpdateRequest
    {
        // Valider que les données demandées sont autorisées
        $allowedFields = ['phone', 'address', 'familyStatus', 'children', 'iban', 'bic'];
        foreach (array_keys($requestedData) as $field) {
            if (!in_array($field, $allowedFields, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Le champ "%s" ne peut pas être modifié via une demande. Contactez l\'administrateur.',
                    $field
                ));
            }
        }

        // Vérifier qu'il n'y a pas déjà une demande en attente pour cet utilisateur
        $existingPendingRequest = $this->entityManager
            ->getRepository(ProfileUpdateRequest::class)
            ->findOneBy([
                'user' => $user,
                'status' => ProfileUpdateRequest::STATUS_PENDING,
            ]);

        if ($existingPendingRequest) {
            throw new \RuntimeException('Vous avez déjà une demande de modification en attente.');
        }

        // Créer la demande
        $request = new ProfileUpdateRequest();
        $request
            ->setUser($user)
            ->setRequestedData($requestedData)
            ->setStatus(ProfileUpdateRequest::STATUS_PENDING)
            ->setReason($reason)
            ->setRequestedAt(new \DateTime());

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        // Envoyer notification à l'admin
        $this->sendRequestNotificationToAdmin($request);

        $this->logger->info('Profile update request created', [
            'request_id' => $request->getId(),
            'user_id' => $user->getId(),
        ]);

        // Dispatcher l'event
        $this->eventDispatcher->dispatch(
            new ProfileUpdateRequestedEvent($request),
            ProfileUpdateRequestedEvent::NAME
        );

        return $request;
    }

    /**
     * Approuve une demande de modification
     * Applique les modifications au profil utilisateur et notifie l'utilisateur
     */
    public function approveRequest(ProfileUpdateRequest $request, User $processedBy): void
    {
        if ($request->getStatus() !== ProfileUpdateRequest::STATUS_PENDING) {
            throw new \LogicException('Seules les demandes en attente peuvent être approuvées.');
        }

        // Marquer la demande comme approuvée
        $request->markAsApproved($processedBy);

        // Appliquer les modifications au profil utilisateur
        $this->userManager->updateUserProfile($request->getUser(), $request->getRequestedData());

        $this->entityManager->flush();

        // Envoyer notification à l'utilisateur
        $this->sendApprovalNotificationToUser($request);

        $this->logger->info('Profile update request approved', [
            'request_id' => $request->getId(),
            'user_id' => $request->getUser()->getId(),
            'processed_by_id' => $processedBy->getId(),
        ]);

        // Dispatcher l'event
        $this->eventDispatcher->dispatch(
            new ProfileUpdateApprovedEvent($request, $processedBy),
            ProfileUpdateApprovedEvent::NAME
        );
    }

    /**
     * Rejette une demande de modification
     * Notifie l'utilisateur du rejet avec la raison
     */
    public function rejectRequest(ProfileUpdateRequest $request, string $reason, User $processedBy): void
    {
        if ($request->getStatus() !== ProfileUpdateRequest::STATUS_PENDING) {
            throw new \LogicException('Seules les demandes en attente peuvent être rejetées.');
        }

        // Marquer la demande comme rejetée
        $request->markAsRejected($reason, $processedBy);

        $this->entityManager->flush();

        // Envoyer notification à l'utilisateur
        $this->sendRejectionNotificationToUser($request);

        $this->logger->info('Profile update request rejected', [
            'request_id' => $request->getId(),
            'user_id' => $request->getUser()->getId(),
            'processed_by_id' => $processedBy->getId(),
            'reason' => $reason,
        ]);
    }

    /**
     * Envoie une notification à l'admin qu'une nouvelle demande a été créée
     */
    private function sendRequestNotificationToAdmin(ProfileUpdateRequest $request): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to($this->adminEmail)
            ->subject('Nouvelle demande de modification de profil')
            ->htmlTemplate('emails/profile_update_request_admin.html.twig')
            ->context([
                'request' => $request,
                'user' => $request->getUser(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie une notification à l'utilisateur que sa demande a été approuvée
     */
    private function sendApprovalNotificationToUser(ProfileUpdateRequest $request): void
    {
        $user = $request->getUser();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Votre demande de modification a été approuvée')
            ->htmlTemplate('emails/profile_update_approved.html.twig')
            ->context([
                'request' => $request,
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie une notification à l'utilisateur que sa demande a été rejetée
     */
    private function sendRejectionNotificationToUser(ProfileUpdateRequest $request): void
    {
        $user = $request->getUser();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Votre demande de modification a été refusée')
            ->htmlTemplate('emails/profile_update_rejected.html.twig')
            ->context([
                'request' => $request,
                'user' => $user,
                'reason' => $request->getReason(),
            ]);

        $this->mailer->send($email);
    }
}
