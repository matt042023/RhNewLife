<?php

namespace App\Controller;

use App\Entity\MessageInterne;
use App\Entity\User;
use App\Repository\MessageInterneRepository;
use App\Service\Communication\MessageInterneService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for users to view their received messages.
 * Different from Admin\MessageInterneController which is for admin features (compose, etc.)
 */
#[Route('/messages')]
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    public function __construct(
        private MessageInterneRepository $repository,
        private MessageInterneService $service
    ) {
    }

    /**
     * User inbox - view received messages
     */
    #[Route('', name: 'app_message_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $messages = $this->repository->findReceivedByUser($user, 1);
        $unreadCount = $this->repository->countUnreadForUser($user);

        return $this->render('message/index.html.twig', [
            'messages' => $messages,
            'unreadCount' => $unreadCount,
            'currentUser' => $user,
        ]);
    }

    /**
     * View a single message (user must be a recipient)
     */
    #[Route('/{id}', name: 'app_message_show', methods: ['GET'])]
    public function show(MessageInterne $message, #[CurrentUser] User $user): Response
    {
        // Check if user is recipient or expediteur
        if (!$this->service->canAccessMessage($message, $user)) {
            $this->addFlash('error', 'Vous n\'avez pas acces a ce message');
            return $this->redirectToRoute('app_message_index');
        }

        // Mark as read if recipient
        if ($message->getDestinataires()->contains($user)) {
            $this->service->markAsRead($message, $user);
        }

        return $this->render('message/show.html.twig', [
            'message' => $message,
            'currentUser' => $user,
        ]);
    }
}
