<?php

namespace App\Service\Communication;

use App\Entity\MessageInterne;
use App\Entity\User;
use App\Repository\MessageInterneRepository;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MessageInterneService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageInterneRepository $messageRepository,
        private UserRepository $userRepository,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Send message to specific users (WF78)
     *
     * @param User[] $destinataires
     * @param int[]|null $piecesJointes Document IDs
     */
    public function sendToUsers(
        User $expediteur,
        array $destinataires,
        string $sujet,
        string $contenu,
        ?array $piecesJointes = null
    ): MessageInterne {
        $message = new MessageInterne();
        $message->setExpediteur($expediteur);
        $message->setSujet($sujet);
        $message->setContenu($contenu);
        $message->setPiecesJointes($piecesJointes);

        foreach ($destinataires as $user) {
            $message->addDestinataire($user);
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        // Create notifications for recipients
        foreach ($destinataires as $user) {
            $this->notificationService->notifyMessageReceived(
                $user,
                $expediteur,
                $message->getId(),
                $sujet
            );
        }

        $this->logger->info('Message interne sent', [
            'message_id' => $message->getId(),
            'expediteur_id' => $expediteur->getId(),
            'destinataires_count' => count($destinataires),
            'sujet' => $sujet,
        ]);

        return $message;
    }

    /**
     * Send message to users with specific roles (R5: Admin broadcast)
     *
     * @param string[] $roles
     * @param int[]|null $piecesJointes Document IDs
     */
    public function sendToRoles(
        User $expediteur,
        array $roles,
        string $sujet,
        string $contenu,
        ?array $piecesJointes = null
    ): MessageInterne {
        $destinataires = [];
        $processedIds = [];

        foreach ($roles as $role) {
            $users = $this->userRepository->findByRole($role);
            foreach ($users as $user) {
                if (!in_array($user->getId(), $processedIds, true)) {
                    $destinataires[] = $user;
                    $processedIds[] = $user->getId();
                }
            }
        }

        $message = $this->sendToUsers($expediteur, $destinataires, $sujet, $contenu, $piecesJointes);
        $message->setRolesCible($roles);
        $this->entityManager->flush();

        $this->logger->info('Broadcast message sent to roles', [
            'message_id' => $message->getId(),
            'roles' => $roles,
            'destinataires_count' => count($destinataires),
        ]);

        return $message;
    }

    /**
     * Send message to all active users
     *
     * @param int[]|null $piecesJointes Document IDs
     */
    public function sendToAllUsers(
        User $expediteur,
        string $sujet,
        string $contenu,
        ?array $piecesJointes = null
    ): MessageInterne {
        $destinataires = $this->userRepository->findAllActive();

        // Remove the sender from recipients if present
        $destinataires = array_filter(
            $destinataires,
            fn(User $u) => $u->getId() !== $expediteur->getId()
        );

        return $this->sendToUsers($expediteur, array_values($destinataires), $sujet, $contenu, $piecesJointes);
    }

    /**
     * Mark message as read by user
     */
    public function markAsRead(MessageInterne $message, User $user): void
    {
        if (!$message->isReadBy($user)) {
            $message->markAsReadBy($user);
            $this->entityManager->flush();

            $this->logger->debug('Message marked as read', [
                'message_id' => $message->getId(),
                'user_id' => $user->getId(),
            ]);
        }
    }

    /**
     * Get inbox messages for user
     *
     * @return MessageInterne[]
     */
    public function getInbox(User $user, int $page = 1, int $limit = 20): array
    {
        return $this->messageRepository->findReceivedByUser($user, $page, $limit);
    }

    /**
     * Get sent messages for user
     *
     * @return MessageInterne[]
     */
    public function getSent(User $user, int $page = 1, int $limit = 20): array
    {
        return $this->messageRepository->findSentByUser($user, $page, $limit);
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(User $user): int
    {
        return $this->messageRepository->countUnreadForUser($user);
    }

    /**
     * Check if user can access message (is sender or recipient)
     */
    public function canAccessMessage(MessageInterne $message, User $user): bool
    {
        // User is sender
        if ($message->getExpediteur() === $user) {
            return true;
        }

        // User is recipient
        if ($message->getDestinataires()->contains($user)) {
            return true;
        }

        return false;
    }

    /**
     * Get recent conversations for a user
     *
     * @return MessageInterne[]
     */
    public function getRecentConversations(User $user, int $limit = 5): array
    {
        return $this->messageRepository->findRecentConversations($user, $limit);
    }
}
