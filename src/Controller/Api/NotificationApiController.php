<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\Notification\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationApiController extends AbstractController
{
    public function __construct(
        private NotificationRepository $repository,
        private NotificationService $notificationService
    ) {
    }

    /**
     * Get unread notifications for header badge/dropdown
     */
    #[Route('/unread', name: 'api_notifications_unread', methods: ['GET'])]
    public function getUnread(#[CurrentUser] User $user): JsonResponse
    {
        $notifications = $this->repository->findUnreadForUser($user, 10);
        $count = $this->repository->countUnreadForUser($user);

        return $this->json([
            'count' => $count,
            'notifications' => array_map(fn($n) => [
                'id' => $n->getId(),
                'type' => $n->getType(),
                'titre' => $n->getTitre(),
                'message' => $n->getMessage(),
                'lien' => $n->getLien(),
                'dateEnvoi' => $n->getDateEnvoi()->format('c'),
                'lu' => $n->isLu(),
            ], $notifications),
        ]);
    }

    /**
     * Mark notification as read
     */
    #[Route('/{id}/read', name: 'api_notification_mark_read', methods: ['POST'])]
    public function markAsRead(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $notification = $this->repository->find($id);

        if (!$notification) {
            return $this->json(['error' => 'Notification not found'], 404);
        }

        if ($notification->getCibleUser() !== $user) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $this->notificationService->markAsRead($notification, $user);

        return $this->json(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    #[Route('/read-all', name: 'api_notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(#[CurrentUser] User $user): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($user);

        return $this->json([
            'success' => true,
            'marked' => $count,
        ]);
    }

    /**
     * Get notification count only (for quick badge update)
     */
    #[Route('/count', name: 'api_notifications_count', methods: ['GET'])]
    public function getCount(#[CurrentUser] User $user): JsonResponse
    {
        $count = $this->repository->countUnreadForUser($user);

        return $this->json(['count' => $count]);
    }
}
