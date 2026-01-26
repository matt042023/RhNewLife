<?php

namespace App\Service\Notification;

use App\Entity\Absence;
use App\Entity\Contract;
use App\Entity\ConsolidationPaie;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create notification for a specific user (WF75)
     */
    public function createForUser(
        User $user,
        string $type,
        string $titre,
        string $message,
        ?string $lien = null,
        ?string $sourceEvent = null,
        ?int $sourceEntityId = null
    ): ?Notification {
        // R1: Check for duplicate
        if ($sourceEvent && $sourceEntityId) {
            if ($this->notificationRepository->existsForSourceEvent($sourceEvent, $sourceEntityId, $user)) {
                $this->logger->debug('Notification already exists, skipping duplicate', [
                    'user_id' => $user->getId(),
                    'source_event' => $sourceEvent,
                    'source_entity_id' => $sourceEntityId,
                ]);
                return null;
            }
        }

        $notification = new Notification();
        $notification->setCibleUser($user);
        $notification->setType($type);
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setLien($lien);
        $notification->setSourceEvent($sourceEvent);
        $notification->setSourceEntityId($sourceEntityId);
        $notification->setDateEnvoi(new \DateTime());

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->logger->info('Notification created', [
            'notification_id' => $notification->getId(),
            'user_id' => $user->getId(),
            'type' => $type,
            'titre' => $titre,
        ]);

        return $notification;
    }

    /**
     * Create notification for users with specific roles (R5: broadcast)
     *
     * @return Notification[]
     */
    public function createForRoles(
        array $roles,
        string $type,
        string $titre,
        string $message,
        ?string $lien = null,
        ?string $sourceEvent = null,
        ?int $sourceEntityId = null
    ): array {
        $notifications = [];
        $processedUserIds = [];

        foreach ($roles as $role) {
            $users = $this->userRepository->findByRole($role);
            foreach ($users as $user) {
                // Avoid duplicates when user has multiple roles
                if (in_array($user->getId(), $processedUserIds, true)) {
                    continue;
                }
                $processedUserIds[] = $user->getId();

                $notification = $this->createForUser(
                    $user,
                    $type,
                    $titre,
                    $message,
                    $lien,
                    $sourceEvent,
                    $sourceEntityId
                );
                if ($notification) {
                    $notifications[] = $notification;
                }
            }
        }

        $this->logger->info('Broadcast notifications created', [
            'roles' => $roles,
            'count' => count($notifications),
        ]);

        return $notifications;
    }

    /**
     * Create notification for all active users
     *
     * @return Notification[]
     */
    public function createForAllUsers(
        string $type,
        string $titre,
        string $message,
        ?string $lien = null,
        ?string $sourceEvent = null,
        ?int $sourceEntityId = null
    ): array {
        $notifications = [];
        $users = $this->userRepository->findAllActive();

        foreach ($users as $user) {
            $notification = $this->createForUser(
                $user,
                $type,
                $titre,
                $message,
                $lien,
                $sourceEvent,
                $sourceEntityId
            );
            if ($notification) {
                $notifications[] = $notification;
            }
        }

        return $notifications;
    }

    /**
     * Mark notification as read (WF76)
     */
    public function markAsRead(Notification $notification, User $user): void
    {
        // R2: Verify user owns notification
        if ($notification->getCibleUser() !== $user) {
            throw new \RuntimeException('User cannot mark this notification as read');
        }

        $notification->markAsRead();
        $this->entityManager->flush();

        $this->logger->debug('Notification marked as read', [
            'notification_id' => $notification->getId(),
            'user_id' => $user->getId(),
        ]);
    }

    /**
     * Mark all user notifications as read
     */
    public function markAllAsRead(User $user): int
    {
        $count = $this->notificationRepository->markAllAsReadForUser($user);

        $this->logger->info('All notifications marked as read', [
            'user_id' => $user->getId(),
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Get unread count for badge
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadForUser($user);
    }

    /**
     * Get unread notifications for dropdown
     *
     * @return Notification[]
     */
    public function getUnreadNotifications(User $user, int $limit = 10): array
    {
        return $this->notificationRepository->findUnreadForUser($user, $limit);
    }

    // =============================================
    // NOTIFICATION FACTORY METHODS FOR EVENTS
    // =============================================

    /**
     * Notify admins when an absence is created
     */
    public function notifyAbsenceCreated(Absence $absence): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        $url = $this->urlGenerator->generate('admin_absence_show', ['id' => $absence->getId()]);

        foreach ($admins as $admin) {
            $this->createForUser(
                $admin,
                Notification::TYPE_ACTION,
                'Nouvelle demande d\'absence',
                sprintf(
                    '%s a soumis une demande d\'absence du %s au %s',
                    $absence->getUser()->getFullName(),
                    $absence->getStartAt()->format('d/m/Y'),
                    $absence->getEndAt()->format('d/m/Y')
                ),
                $url,
                Notification::SOURCE_ABSENCE_CREATED,
                $absence->getId()
            );
        }
    }

    /**
     * Notify user when their absence is validated
     */
    public function notifyAbsenceValidated(Absence $absence): void
    {
        $url = $this->urlGenerator->generate('app_absence_index');

        $this->createForUser(
            $absence->getUser(),
            Notification::TYPE_INFO,
            'Absence validee',
            sprintf(
                'Votre demande d\'absence du %s au %s a ete validee',
                $absence->getStartAt()->format('d/m/Y'),
                $absence->getEndAt()->format('d/m/Y')
            ),
            $url,
            Notification::SOURCE_ABSENCE_VALIDATED,
            $absence->getId()
        );
    }

    /**
     * Notify user when their absence is rejected
     */
    public function notifyAbsenceRejected(Absence $absence, ?string $reason = null): void
    {
        $url = $this->urlGenerator->generate('app_absence_index');

        $message = sprintf(
            'Votre demande d\'absence du %s au %s a ete refusee',
            $absence->getStartAt()->format('d/m/Y'),
            $absence->getEndAt()->format('d/m/Y')
        );

        if ($reason) {
            $message .= '. Motif: ' . $reason;
        }

        $this->createForUser(
            $absence->getUser(),
            Notification::TYPE_ALERTE,
            'Absence refusee',
            $message,
            $url,
            Notification::SOURCE_ABSENCE_REJECTED,
            $absence->getId()
        );
    }

    /**
     * Notify user when a contract is created for them
     */
    public function notifyContractCreated(Contract $contract): void
    {
        $url = $this->urlGenerator->generate('app_contract_signature', [
            'token' => $contract->getSignatureToken(),
        ]);

        $this->createForUser(
            $contract->getUser(),
            Notification::TYPE_ACTION,
            'Nouveau contrat a signer',
            sprintf(
                'Un contrat %s a ete cree pour vous. Veuillez le consulter et le signer.',
                $contract->getTypeLabel()
            ),
            $url,
            Notification::SOURCE_CONTRACT_CREATED,
            $contract->getId()
        );
    }

    /**
     * Notify admins when a contract is signed
     */
    public function notifyContractSigned(Contract $contract): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        $url = $this->urlGenerator->generate('admin_contracts_show', ['id' => $contract->getId()]);

        foreach ($admins as $admin) {
            $this->createForUser(
                $admin,
                Notification::TYPE_INFO,
                'Contrat signe',
                sprintf(
                    '%s a signe son contrat %s',
                    $contract->getUser()->getFullName(),
                    $contract->getTypeLabel()
                ),
                $url,
                Notification::SOURCE_CONTRACT_SIGNED,
                $contract->getId()
            );
        }
    }

    /**
     * Notify user when their payroll is validated
     */
    public function notifyPayrollValidated(ConsolidationPaie $consolidation): void
    {
        $url = $this->urlGenerator->generate('app_my_payroll_show', [
            'id' => $consolidation->getId(),
        ]);

        $this->createForUser(
            $consolidation->getUser(),
            Notification::TYPE_INFO,
            'Rapport de paie disponible',
            sprintf(
                'Votre rapport de paie pour la periode %s est maintenant disponible.',
                $consolidation->getPeriod()
            ),
            $url,
            Notification::SOURCE_PAYROLL_VALIDATED,
            $consolidation->getId()
        );
    }

    /**
     * Notify about message received
     */
    public function notifyMessageReceived(User $recipient, User $sender, int $messageId, string $subject): void
    {
        // Use user-accessible route (not admin) so all users can view their messages
        $url = $this->urlGenerator->generate('app_message_show', ['id' => $messageId]);

        $this->createForUser(
            $recipient,
            Notification::TYPE_INFO,
            'Nouveau message de ' . $sender->getFullName(),
            $subject,
            $url,
            Notification::SOURCE_MESSAGE_RECEIVED,
            $messageId
        );
    }

    /**
     * Notify about announcement published
     */
    public function notifyAnnoncePublished(User $user, int $annonceId, string $titre): void
    {
        $this->createForUser(
            $user,
            Notification::TYPE_INFO,
            'Nouvelle annonce: ' . $titre,
            'Une nouvelle annonce a ete publiee. Consultez-la sur votre tableau de bord.',
            null,
            Notification::SOURCE_ANNONCE_PUBLISHED,
            $annonceId
        );
    }
}
