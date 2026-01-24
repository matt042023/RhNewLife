<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\Notification\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationRepository $repository,
        private NotificationService $service
    ) {}

    /**
     * Notification center - full list
     */
    #[Route('', name: 'app_notifications_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $notifications = $this->repository->findForUserPaginated($user, $page, $limit);
        $unreadCount = $this->repository->countUnreadForUser($user);
        $totalCount = $this->repository->countForUser($user);
        $totalPages = (int) ceil($totalCount / $limit);

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'totalCount' => $totalCount,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * Mark notification as read and redirect to link
     */
    #[Route('/{id}/go', name: 'app_notification_go', methods: ['GET'])]
    public function goToNotification(int $id, #[CurrentUser] User $user): Response
    {
        $notification = $this->repository->find($id);

        if (!$notification || $notification->getCibleUser() !== $user) {
            $this->addFlash('error', 'Notification non trouvee');
            return $this->redirectToRoute('app_notifications_index');
        }

        // Mark as read
        if (!$notification->isLu()) {
            $this->service->markAsRead($notification, $user);
        }

        // Redirect to link or back to notifications
        if ($notification->getLien()) {
            return $this->redirect($notification->getLien());
        }

        return $this->redirectToRoute('app_notifications_index');
    }

    /**
     * Mark single notification as read
     */
    #[Route('/{id}/read', name: 'app_notification_mark_read', methods: ['POST'])]
    public function markAsRead(int $id, #[CurrentUser] User $user, Request $request): Response
    {
        $notification = $this->repository->find($id);

        if (!$notification || $notification->getCibleUser() !== $user) {
            $this->addFlash('error', 'Notification non trouvee');
            return $this->redirectToRoute('app_notifications_index');
        }

        $this->service->markAsRead($notification, $user);
        $this->addFlash('success', 'Notification marquee comme lue');

        // Redirect back to referer or notifications page
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_notifications_index');
    }

    /**
     * Mark all notifications as read
     */
    #[Route('/read-all', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(#[CurrentUser] User $user): Response
    {
        $count = $this->service->markAllAsRead($user);

        $this->addFlash('success', sprintf('%d notification(s) marquee(s) comme lue(s)', $count));

        return $this->redirectToRoute('app_notifications_index');
    }
}
